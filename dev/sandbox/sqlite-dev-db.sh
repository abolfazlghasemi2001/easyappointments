#!/usr/bin/env bash
#
# Development helper: creates a local SQLite database for the checkout.
#
# The official migrations target MySQL/MariaDB (they use storage engines, foreign keys and column modifications that
# SQLite does not support), so the migrations are executed on a temporary copy of the project where the SQLite driver
# ignores the MySQL specific statements. The resulting database file is then copied into the real storage folder.
#
# The temporary copy is only used for the installation, the sources of the checkout are never modified.
#
# Requires: python3 and the php-wasm development runtime of dev/sandbox/setup.sh.
#
# Usage: bash dev/sandbox/sqlite-dev-db.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

BOOT_DIR="${EA_BOOT_DIR:-/tmp/ea-boot}"

rm -rf "$BOOT_DIR"
cp -a "$ROOT" "$BOOT_DIR"
rm -rf "$BOOT_DIR/.git" "$BOOT_DIR/node_modules" "$BOOT_DIR/dev/sandbox/node_modules"

python3 - "$BOOT_DIR" <<'PY'
import sys

boot = sys.argv[1]

forge = boot + '/system/database/drivers/sqlite3/sqlite3_forge.php'

source = open(forge, encoding='utf-8').read()

if 'SANDBOX-ONLY SHIM' not in source:
    anchor = """	/**
	 * Class constructor
	 *
	 * @param	object	&$db	Database object
	 * @return	void
	 */
	public function __construct(&$db)"""

    shim = """	/**
	 * SANDBOX-ONLY SHIM: storage attributes (ENGINE/CHARSET) are not supported by SQLite.
	 */
	protected function _create_table_attr($attributes)
	{
		return '';
	}

	/**
	 * SANDBOX-ONLY SHIM: SQLite has dynamic column types, so MODIFY is a no-op and renames are translated to
	 * RENAME COLUMN.
	 */
	public function modify_column($table, $field)
	{
		is_array($field) OR $field = [$field];

		foreach ($field as $key => $definition)
		{
			$new_name = is_array($definition) ? ($definition['name'] ?? $key) : $key;

			if ($new_name !== $key)
			{
				$this->db->query(
					'ALTER TABLE '.$this->db->escape_identifiers($this->db->dbprefix.$table)
					.' RENAME COLUMN '.$this->db->escape_identifiers($key)
					.' TO '.$this->db->escape_identifiers($new_name),
				);
			}
		}

		return TRUE;
	}

	/**
	 * SANDBOX-ONLY SHIM: SQLite 3.35+ supports DROP COLUMN.
	 */
	public function drop_column($table, $column_name)
	{
		foreach ((array) $column_name as $name)
		{
			$this->db->query(
				'ALTER TABLE '.$this->db->escape_identifiers($this->db->dbprefix.$table)
				.' DROP COLUMN '.$this->db->escape_identifiers($name),
			);
		}

		return TRUE;
	}

""" + anchor

    assert anchor in source, 'The SQLite forge anchor could not be found.'

    open(forge, 'w', encoding='utf-8').write(source.replace(anchor, shim, 1))

driver = boot + '/system/database/drivers/sqlite3/sqlite3_driver.php'

source = open(driver, encoding='utf-8').read()

if 'mysql_only' not in source:
    anchor = """	protected function _execute($sql)
	{
		return $this->is_write_type($sql)
			? $this->conn_id->exec($sql)
			: $this->conn_id->query($sql);
	}"""

    shim = """	protected function _execute($sql)
	{
		// SANDBOX-ONLY SHIM: the migrations also contain MySQL specific statements, which are skipped here.
		$mysql_only = [
			'/^\\s*ALTER\\s+TABLE\\b.*\\bFOREIGN\\s+KEY\\b/is',
			'/^\\s*SET\\s+FOREIGN_KEY_CHECKS\\b/is',
			'/^\\s*ALTER\\s+TABLE\\b.*\\bCONVERT\\s+TO\\s+CHARACTER\\s+SET\\b/is',
			'/^\\s*ALTER\\s+TABLE\\b.*\\bENGINE\\s*=/is',
		];

		foreach ($mysql_only as $pattern) {
			if (preg_match($pattern, $sql)) {
				return TRUE;
			}
		}

		$sql = preg_replace('/\\s+ENGINE\\s*=\\s*\\w+/i', '', $sql);

		return $this->is_write_type($sql)
			? $this->conn_id->exec($sql)
			: $this->conn_id->query($sql);
	}"""

    assert anchor in source, 'The SQLite driver anchor could not be found.'

    open(driver, 'w', encoding='utf-8').write(source.replace(anchor, shim, 1))

print('Applied the SQLite development shims to the temporary copy.')
PY

python3 "$ROOT/dev/sandbox/minicomposer.py" "$BOOT_DIR" >/dev/null

EA_ROOT="$BOOT_DIR" node "$ROOT/dev/sandbox/run.mjs" index.php console install

cp "$BOOT_DIR/storage/easyappointments.sqlite" "$ROOT/storage/easyappointments.sqlite"

echo "The development database was created at storage/easyappointments.sqlite"
echo "(the temporary copy is available at $BOOT_DIR)"
