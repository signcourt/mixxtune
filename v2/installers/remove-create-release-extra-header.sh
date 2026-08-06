#!/usr/bin/env bash

set -Eeuo pipefail

cd /var/www/backstage-distribution

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="v2/backups/remove-release-header/$STAMP"

mkdir -p "$BACKUP"

echo "=============================================="
echo "LOCATING LIVE CREATE RELEASE COMPONENT"
echo "=============================================="

mapfile -t FILES < <(
    grep -RIl \
        --include='*.jsx' \
        --include='*.js' \
        --include='*.tsx' \
        --include='*.ts' \
        -E \
        "One shared release flow for Artist|Auto-save starts after first save" \
        resources/js
)

if [ "${#FILES[@]}" -eq 0 ]; then
    echo "ERROR: Live text वाली frontend file नहीं मिली।"
    echo ""
    echo "Create Release related files:"
    find resources/js -type f | grep -Ei \
        'release.*(create|wizard)|create.*release' \
        | sort
    exit 1
fi

echo ""
echo "Files found:"

printf '%s\n' "${FILES[@]}"

for FILE in "${FILES[@]}"; do
    mkdir -p "$BACKUP/$(dirname "$FILE")"
    cp -a "$FILE" "$BACKUP/$FILE"
done

python3 - <<'PY'
from pathlib import Path
import re

extensions = {'.js', '.jsx', '.ts', '.tsx'}

files = []

for path in Path('resources/js').rglob('*'):
    if (
        path.is_file()
        and path.suffix in extensions
    ):
        text = path.read_text(errors='ignore')

        if (
            'One shared release flow for Artist' in text
            or 'Auto-save starts after first save' in text
        ):
            files.append(path)


def enclosing_div_block(source: str, position: int):
    """
    Find the nearest JSX <div>...</div> containing position.
    """
    token_pattern = re.compile(
        r'<div\b[^>]*>|</div>',
        re.S
    )

    stack = []
    candidates = []

    for match in token_pattern.finditer(source):
        token = match.group(0)

        if token.startswith('<div'):
            stack.append(
                (match.start(), match.end())
            )
        elif stack:
            start, open_end = stack.pop()
            end = match.end()

            if start <= position <= end:
                candidates.append(
                    (start, end)
                )

    if not candidates:
        return None

    # Nearest/smallest containing div.
    return min(
        candidates,
        key=lambda item: item[1] - item[0]
    )


for path in files:
    text = path.read_text()
    original = text

    # ------------------------------------------
    # Remove large page intro:
    # Create Release
    # One shared release flow...
    # Cancel
    # ------------------------------------------
    phrase = (
        'One shared release flow for Artist, '
        'Label, Admin and Super Admin.'
    )

    index = text.find(phrase)

    if index == -1:
        index = text.find(
            'One shared release flow for Artist'
        )

    if index != -1:
        block = enclosing_div_block(
            text,
            index
        )

        if block:
            start, end = block
            candidate = text[start:end]

            # Move outward when nearest div is only subtitle.
            for _ in range(4):
                if (
                    'Create Release' in candidate
                    and (
                        'Cancel' in candidate
                        or 'shared release flow' in candidate
                    )
                ):
                    break

                parent = enclosing_div_block(
                    text,
                    max(start - 1, 0)
                )

                if (
                    not parent
                    or parent == (start, end)
                ):
                    break

                start, end = parent
                candidate = text[start:end]

            if (
                'Create Release' in candidate
                and 'shared release flow' in candidate
            ):
                text = (
                    text[:start]
                    + '\n'
                    + text[end:]
                )

                print(
                    f'Removed intro header from: {path}'
                )

    # ------------------------------------------
    # Remove only old Back button and autosave text.
    # Keep Save Draft / Next buttons temporarily.
    # ------------------------------------------
    text = re.sub(
        r'''
        <button
        (?:
            (?!</button>).|\n
        )*?
        (?:
            ←\s*Back|
            >\s*Back\s*<
        )
        (?:
            (?!</button>).|\n
        )*?
        </button>
        ''',
        '',
        text,
        count=1,
        flags=re.X | re.S,
    )

    text = re.sub(
        r'''
        <[^>]+>
        \s*
        Auto-save\ starts\ after\ first\ save
        \s*
        </[^>]+>
        ''',
        '',
        text,
        count=1,
        flags=re.X | re.S,
    )

    text = text.replace(
        'Auto-save starts after first save',
        ''
    )

    text = text.replace(
        'Auto save starts after first save',
        ''
    )

    if text != original:
        path.write_text(text)
        print(f'Updated: {path}')
    else:
        print(f'No change required: {path}')
PY

echo ""
echo "=============================================="
echo "VERIFYING REMOVED TEXT"
echo "=============================================="

if grep -RIn \
    --include='*.jsx' \
    --include='*.js' \
    -E \
    "One shared release flow for Artist|Auto-save starts after first save" \
    resources/js
then
    echo ""
    echo "ERROR: Text अभी भी किसी frontend file में मौजूद है।"
    exit 1
else
    echo "Unwanted text successfully removed."
fi

echo ""
echo "=============================================="
echo "BUILDING FRONTEND"
echo "=============================================="

npm run build

php artisan optimize:clear

printf '{\n  "module": "RemoveCreateReleaseExtraHeader",\n  "installed": true,\n  "installed_at": "%s"\n}\n' \
    "$(date --iso-8601=seconds)" \
    > v2/runtime/state/remove-create-release-extra-header.json

echo ""
echo "=============================================="
echo "CREATE RELEASE EXTRA HEADER REMOVED"
echo "=============================================="

echo "Backup:"
echo "/var/www/backstage-distribution/$BACKUP"
