#!/bin/sh
set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$repo_dir"

if [ ! -f vendor/autoload.php ]; then
    echo 'Сначала выполните composer install из корня пакета.' >&2
    exit 1
fi
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
    echo 'Для test:redis нужен Docker с Compose (например, Docker Desktop).' >&2
    exit 1
fi
if ! docker info >/dev/null 2>&1; then
    echo 'Docker недоступен. Запустите Docker Desktop или Docker Engine.' >&2
    exit 1
fi

# Отдельный проект на запуск: параллельные проверки не разделяют Redis и очистку.
project_name="apisutra-redis-$(date +%s)-$$"
compose_file="$repo_dir/tests/Integration/Redis/compose.yaml"
compose() {
    docker compose --project-name "$project_name" --file "$compose_file" "$@"
}
cleanup() {
    test_status=$?
    trap - 0
    if ! compose down --volumes --remove-orphans; then
        echo "Не удалось очистить тестовый стенд $project_name." >&2
        if [ "$test_status" -eq 0 ]; then test_status=1; fi
    fi
    exit "$test_status"
}
trap cleanup 0
trap 'exit 130' INT
trap 'exit 143' TERM

echo "Redis test project: $project_name"
# Compose ожидает healthcheck Redis; аргументы Composer передаются непосредственно Pest.
compose run --build --rm -T tests "$@"
