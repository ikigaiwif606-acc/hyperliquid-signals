#!/usr/bin/env bash
# Rebuild Tailwind CSS. Run whenever you add/remove utility classes in PHP templates.
set -euo pipefail
cd "$(dirname "$0")"
npx -y tailwindcss@3 \
    -c tailwind.config.js \
    -i public/assets/tailwind.input.css \
    -o public/assets/tailwind.css \
    --minify
echo "wrote $(wc -c < public/assets/tailwind.css) bytes → public/assets/tailwind.css"
