#!/usr/bin/env bash
set -euo pipefail
umask 077

if [[ $# -ne 4 ]]; then
    printf 'Usage: bash bin/backup-crm.sh DATABASE MYSQL_CLIENT_CONFIG ATTACHMENT_ROOT BACKUP_DIR\n' >&2
    exit 2
fi

database=$1
client_config=$2
attachment_root=$3
destination=$4

if [[ ! "$database" =~ ^[A-Za-z0-9_-]+$ || ! -f "$client_config" || ! -d "$attachment_root" || ! -d "$destination" ]]; then
    printf 'Invalid database name, client configuration, attachment root or backup directory.\n' >&2
    exit 2
fi

attachment_root=$(cd -- "$attachment_root" && pwd -P)
destination=$(cd -- "$destination" && pwd -P)
public_root=$(cd -- "$(dirname -- "$0")/../public" && pwd -P)

case "$destination/" in
    "$attachment_root/"*|"$public_root/"*)
        printf 'The backup directory must be outside the attachment and web roots.\n' >&2
        exit 2
        ;;
esac

work_dir=$(mktemp -d "$destination/.crm-backup.XXXXXXXX")
cleanup() {
    rm -f -- "$work_dir/database.sql" "$work_dir/attachments.tar.gz" "$work_dir/SHA256SUMS" "$work_dir/backup.tar.gz"
    rmdir -- "$work_dir"
}
trap cleanup EXIT

"${CRM_MYSQLDUMP:-mysqldump}" --defaults-extra-file="$client_config" --single-transaction --quick --no-tablespaces -- "$database" > "$work_dir/database.sql"
test -s "$work_dir/database.sql"

tar -czf "$work_dir/attachments.tar.gz" -C "$attachment_root" .
(
    cd -- "$work_dir"
    sha256sum database.sql attachments.tar.gz > SHA256SUMS
    tar -czf backup.tar.gz SHA256SUMS database.sql attachments.tar.gz
)

suffix=${work_dir##*.}
archive="$destination/crm-$(date -u +%Y%m%dT%H%M%SZ)-$suffix.tar.gz"
mv -- "$work_dir/backup.tar.gz" "$archive"
printf '%s\n' "$archive"
