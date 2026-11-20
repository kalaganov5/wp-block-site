#!/usr/bin/env bash
# Ищет секреты (ключи, токены, пароли) в git — gitleaks в Docker.
#   wordpress/tools/scan-secrets.sh           вся история коммитов (так запускает CI)
#   wordpress/tools/scan-secrets.sh --staged  только то, что сейчас добавлено в коммит (хук pre-commit)
# Файлы из .gitignore не проверяются: в git они не попадут.
# Зачем: ключ, попавший в историю, остаётся в ней навсегда — его приходится перевыпускать.
set -euo pipefail

# Образ закреплён по отпечатку (digest): тег можно переставить на другой образ, отпечаток — нет.
# Обновление: docker buildx imagetools inspect ghcr.io/gitleaks/gitleaks:<новая версия> → строка Digest.
GITLEAKS_IMAGE="ghcr.io/gitleaks/gitleaks:v8.30.1@sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f"
MODE_ARGS=()
if [[ "${1:-}" == "--staged" ]]; then
	MODE_ARGS=(--pre-commit --staged)
fi

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
	echo "Проверка секретов пропущена: Docker не запущен. В CI она выполняется всегда." >&2
	exit 0
fi

# На Windows (Git Bash) путь для Docker нужен в виде C:/..., без подмены путей MSYS.
REPO_PATH="$(pwd -W 2>/dev/null || pwd)"
MSYS_NO_PATHCONV=1 docker run --rm -v "${REPO_PATH}:/repo" "${GITLEAKS_IMAGE}" \
	git /repo "${MODE_ARGS[@]}" --redact --no-banner
