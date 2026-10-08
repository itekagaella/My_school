<?php
/**
 * Sauvegarde et restauration de la base de données.
 *
 * Génère un dump SQL complet (structure + données) sans dépendre de pg_dump,
 * l'écrit dans storage/backups/ et sait le réimporter (critères 39 et 40).
 */

/**
 * Répertoire des sauvegardes (créé à la demande).
 */
function backup_dir(): string
{
    $dir = ROOT_PATH . 'storage/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Liste des dumps présents (les plus récents en premier).
 */
function backup_list(): array
{
    $dir = backup_dir();
    if (!is_dir($dir)) return [];
    $files = [];
    foreach (glob($dir . '/*.sql') ?: [] as $path) {
        $files[] = [
            'name'  => basename($path),
            'path'  => $path,
            'mtime' => (int)@filemtime($path),
            'size'  => (int)@filesize($path),
            'auto'  => strpos(basename($path), 'sauvegarde_auto_') === 0,
        ];
    }
    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $files;
}

/**
 * Échappe un identifiant SQL (table, colonne).
 */
function backup_ident(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

/**
 * Nom du type SQL d'une colonne (information_schema).
 */
function backup_column_type(array $c): string
{
    $type = $c['data_type'];
    switch ($type) {
        case 'character varying':
            return $c['character_maximum_length'] ? 'VARCHAR(' . (int)$c['character_maximum_length'] . ')' : 'TEXT';
        case 'character':
            return 'CHAR(' . (int)$c['character_maximum_length'] . ')';
        case 'numeric':
            if ($c['numeric_precision']) {
                return 'NUMERIC(' . (int)$c['numeric_precision'] . ',' . (int)($c['numeric_scale'] ?? 0) . ')';
            }
            return 'NUMERIC';
        case 'integer':
        case 'bigint':
        case 'smallint':
        case 'text':
        case 'boolean':
        case 'date':
        case 'jsonb':
        case 'json':
        case 'uuid':
            return strtoupper(str_replace(' ', '_', $type));
        case 'timestamp without time zone':
            return 'TIMESTAMP';
        case 'timestamp with time zone':
            return 'TIMESTAMPTZ';
        case 'time without time zone':
            return 'TIME';
        case 'time with time zone':
            return 'TIMETZ';
        case 'ARRAY':
            return $c['udt_name'] . '[]';
        case 'USER-DEFINED':
            return $c['udt_name'];
        default:
            return $type;
    }
}

/**
 * Formate une valeur lue depuis PDO pour être réinjectée en SQL.
 */
function backup_literal(?string $value, string $dataType): string
{
    if ($value === null) return 'NULL';
    switch ($dataType) {
        case 'boolean':
            return in_array($value, ['t', 'true', '1', 'y', 'on'], true) ? 'TRUE' : 'FALSE';
        case 'integer':
        case 'bigint':
        case 'smallint':
        case 'numeric':
        case 'real':
        case 'double precision':
            if (is_numeric($value)) return $value;
            return "'" . str_replace("'", "''", $value) . "'";
        default:
            return "'" . str_replace("'", "''", $value) . "'";
    }
}

/**
 * Génère un dump SQL complet : structure (tables, contraintes, index)
 * puis données (INSERT groupés), puis remise à zéro des séquences.
 */
function backup_generate_sql(): string
{
    $pdo = getDB();

    $tables = $pdo->query(
        "SELECT tablename FROM pg_catalog.pg_tables
         WHERE schemaname = 'public' ORDER BY tablename"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        throw new RuntimeException('Aucune table trouvée dans le schéma public.');
    }

    $out = [];
    $out[] = '-- ===========================================================';
    $out[] = '-- My_School - Sauvegarde complète de la base de données';
    $out[] = '-- Généré le ' . date('Y-m-d H:i:s');
    $out[] = '-- Restaurable depuis Admin > Paramètres > Sauvegardes';
    $out[] = '-- ===========================================================';
    $out[] = '';
    $out[] = 'BEGIN;';

    // ---- Structure ----
    foreach ($tables as $t) {
        $out[] = 'DROP TABLE IF EXISTS public.' . backup_ident($t) . ' CASCADE;';
    }
    $out[] = '';

    $fkStatements = [];

    foreach ($tables as $t) {
        $q = $pdo->prepare(
            "SELECT column_name, data_type, udt_name, is_nullable, column_default,
                    character_maximum_length, numeric_precision, numeric_scale,
                    is_identity
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t
             ORDER BY ordinal_position"
        );
        $q->execute([':t' => $t]);
        $columns = $q->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) continue;

        $colLines = [];
        foreach ($columns as $c) {
            $line = '    ' . backup_ident($c['column_name']) . ' ' . backup_column_type($c);
            $default = $c['column_default'] ?? '';
            if (($c['is_identity'] ?? 'NO') === 'YES' || preg_match("/^nextval\('/", $default)) {
                // Identity ou serial : recréé en GENERATED BY DEFAULT AS IDENTITY
                // (la séquence est liée à la colonne et repart de 1)
                $line .= ' GENERATED BY DEFAULT AS IDENTITY';
            } elseif ($default !== '') {
                $line .= ' DEFAULT ' . $default;
            }
            if ($c['is_nullable'] === 'NO') $line .= ' NOT NULL';
            $colLines[] = $line;
        }

        $out[] = 'CREATE TABLE public.' . backup_ident($t) . " (\n" . implode(",\n", $colLines) . "\n);";

        // Contraintes hors FK (PK, UNIQUE, CHECK) + index
        $cq = $pdo->prepare(
            "SELECT conname, pg_get_constraintdef(oid) AS def, contype
             FROM pg_constraint
             WHERE conrelid = ('public.' || quote_ident(:t))::regclass
             ORDER BY contype"
        );
        $cq->execute([':t' => $t]);
        foreach ($cq->fetchAll(PDO::FETCH_ASSOC) as $cons) {
            $stmt = 'ALTER TABLE public.' . backup_ident($t)
                  . ' ADD CONSTRAINT ' . backup_ident($cons['conname'])
                  . ' ' . $cons['def'] . ';';
            if ($cons['contype'] === 'f') {
                $fkStatements[] = $stmt; // après création de toutes les tables
            } else {
                $out[] = $stmt;
            }
        }

        // Index non liés à une contrainte (les index de PK/UNIQUE/CHECK
        // sont déjà recréés via pg_constraint)
        $iq = $pdo->prepare(
            "SELECT indexname, indexdef FROM pg_indexes
             WHERE schemaname = 'public' AND tablename = :t"
        );
        $iq->execute([':t' => $t]);
        foreach ($iq->fetchAll(PDO::FETCH_ASSOC) as $idx) {
            $isOwned = $pdo->prepare(
                'SELECT 1 FROM pg_constraint WHERE conindid = (quote_ident(:i)::regclass)'
            );
            $isOwned->execute([':i' => $idx['indexname']]);
            if ($isOwned->fetchColumn()) continue;
            $out[] = rtrim($idx['indexdef'], ';') . ';';
        }
        $out[] = '';
    }

    // ---- Données ----
    // Les clés étrangères ne sont recréées qu'après l'import : l'ordre des
    // tables n'a alors plus aucune importance (y compris en cas de cycle FK).
    foreach ($tables as $t) {
        $q = $pdo->prepare(
            "SELECT column_name, data_type FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t
             ORDER BY ordinal_position"
        );
        $q->execute([':t' => $t]);
        $colInfo = $q->fetchAll(PDO::FETCH_ASSOC);
        if (!$colInfo) continue;

        $colNames = array_map(fn($c) => backup_ident($c['column_name']), $colInfo);
        $types = [];
        foreach ($colInfo as $c) $types[$c['column_name']] = $c['data_type'];

        $stmt = $pdo->query('SELECT * FROM public.' . backup_ident($t));
        $batch = [];
        $inserted = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($colInfo as $c) {
                $values[] = backup_literal($row[$c['column_name']] ?? null, $c['data_type']);
            }
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) >= 200) {
                $out[] = 'INSERT INTO public.' . backup_ident($t) . ' (' . implode(', ', $colNames) . ') VALUES';
                $out[] = implode(",\n", $batch) . ';';
                $batch = [];
                $inserted = true;
            }
        }
        if ($batch) {
            $out[] = 'INSERT INTO public.' . backup_ident($t) . ' (' . implode(', ', $colNames) . ') VALUES';
            $out[] = implode(",\n", $batch) . ';';
            $inserted = true;
        }
        if ($inserted) $out[] = '';
    }

    // ---- Contraintes de clés étrangères (après import des données) ----
    if ($fkStatements) {
        $out[] = '-- Contraintes de clés étrangères';
        $out = array_merge($out, $fkStatements, ['']);
    }

    // ---- Séquences / identity : repositionnement après insertion ----
    foreach ($tables as $t) {
        $q = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t
               AND (column_default LIKE 'nextval(%'
                    OR is_identity = 'YES')"
        );
        $q->execute([':t' => $t]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $col) {
            // setval uniquement si la table contient des lignes (sinon la
            // séquence recréée par la DDL démarre déjà à la bonne valeur)
            $out[] = "SELECT setval(pg_get_serial_sequence('"
                  . str_replace("'", "''", $t) . "', '"
                  . str_replace("'", "''", $col) . "'), g.v, true)"
                  . ' FROM (SELECT MAX(' . backup_ident($col) . ') AS v FROM public.' . backup_ident($t) . ') g'
                  . ' WHERE g.v IS NOT NULL AND g.v > 0;';
        }
    }

    $out[] = '';
    $out[] = 'COMMIT;';
    $out[] = '';

    return implode("\n", $out);
}

/**
 * Écrit un dump dans storage/backups/ et applique une rotation (15 fichiers max).
 * Retourne le chemin du fichier, ou null en cas d'échec.
 */
function backup_write(string $sql, string $prefix = 'sauvegarde_'): ?string
{
    $dir = backup_dir();
    if (!is_dir($dir) || !is_writable($dir)) return null;

    $path = $dir . '/' . $prefix . date('Ymd_His') . '.sql';
    if (@file_put_contents($path, $sql) === false) return null;

    // Rotation : on ne garde que les 15 sauvegardes les plus récentes
    $files = backup_list();
    foreach (array_slice($files, 15) as $old) {
        @unlink($old['path']);
    }
    return $path;
}

/**
 * Sauvegarde automatique : si le paramètre `sauvegarde_auto` est activé et
 * qu'aucun dump n'a été produit depuis $maxAge secondes (24 h par défaut).
 * Retourne le chemin du dump créé, ou null si rien n'a été fait.
 */
function backup_auto(int $maxAge = 86400): ?string
{
    try {
        if (get_param('sauvegarde_auto', 'false') !== 'true') return null;

        $files = backup_list();
        if ($files) {
            $newest = 0;
            foreach ($files as $f) $newest = max($newest, $f['mtime']);
            if (time() - $newest < $maxAge) return null;
        }

        $path = backup_write(backup_generate_sql(), 'sauvegarde_auto_');
        if ($path) {
            log_activity('parametres.backup_auto', 'Sauvegarde automatique générée (' . basename($path) . ')');
        }
        return $path;
    } catch (Throwable $e) {
        error_log('Sauvegarde automatique échouée : ' . $e->getMessage());
        return null;
    }
}

/**
 * Restaure un dump SQL dans la base courante.
 * Le dump est exécuté en une seule transaction (BEGIN/COMMIT du dump inclus) :
 * en cas d'erreur, PostgreSQL annule tout.
 *
 * @return array [bool succès, string message]
 */
function backup_restore(string $sql): array
{
    $sql = trim($sql);
    if ($sql === '') {
        return [false, 'Fichier vide ou invalide.'];
    }
    // Garde-fous : on n'importe que des dumps My_School (ou SQL équivalent
    // structurant le schéma public), jamais n'importe quel script SQL.
    $looksLikeDump = strpos($sql, 'My_School - Sauvegarde') !== false
        || (preg_match('/CREATE TABLE (IF NOT EXISTS )?public\./i', $sql)
            && preg_match('/(INSERT INTO|COPY) public\./i', $sql));
    if (!$looksLikeDump) {
        return [false, 'Le fichier ne semble pas être un dump My_School.'];
    }

    try {
        $pdo = getDB();
        // Les dumps générés par l'application portent leur propre BEGIN/COMMIT ;
        // sinon on enveloppe l'import dans une transaction pour qu'il soit atomique.
        $ownTx = (bool)preg_match('/^\s*BEGIN\s*;/i', $sql);
        if (!$ownTx) $pdo->exec('BEGIN');
        try {
            $pdo->exec($sql);
            if (!$ownTx) $pdo->exec('COMMIT');
        } catch (PDOException $e) {
            if (!$ownTx) {
                try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignore) {}
            }
            throw $e;
        }
        return [true, 'Base de données restaurée avec succès.'];
    } catch (PDOException $e) {
        return [false, 'Échec de la restauration : ' . $e->getMessage()];
    }
}
