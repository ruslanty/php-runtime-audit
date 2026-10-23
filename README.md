# PHP Runtime Audit

Checks PHP image tags in Dockerfiles and YAML manifests against the fixed
upstream releases for PHP2Shell (CVE-2026-109618 and CVE-2026-109619).

The scan reads files from disk. It doesn't run the project being scanned or
connect to its containers or servers.

## Run it

You need PHP 8.5 or later in the 8.x series and Composer 2, or Docker.

With PHP:

```sh
composer install
php bin/php-runtime-audit scan /path/to/your/project
```

Add `--format=json` for JSON output:

```sh
php bin/php-runtime-audit scan /path/to/your/project --format=json
```

With Docker:

```sh
docker build -t php-runtime-audit .
docker run --rm \
  -v "/path/to/your/project:/scan:ro" \
  php-runtime-audit scan /scan
```

## Results

Each result includes the image tag, file and line, advisory IDs, and a short
reason. For example:

```text
Dockerfile:1: POTENTIALLY VULNERABLE php:8.5.11-fpm [CVE-2026-109618, CVE-2026-109619] - Tag is below the fixed release.
```

- `potentially_vulnerable` means the explicit upstream version is below its
  fixed release, or its branch has no listed fixed release.
- `ok` means the tag meets that version threshold. The image itself was not
  inspected.
- `unknown` means the image version could not be verified, for example when
  the image uses a build argument or pinned digest.

JSON output uses `schema_version: 1`. Its `line` field is `null` if the source
line can't be identified reliably.

Exit codes: `0` no affected or unknown tags; `1` potentially vulnerable tag;
`2` invalid input or scan error; `3` at least one unknown result and no
potentially vulnerable result.

## Scope and limits

The catalog uses the [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5).
The scanner checks Dockerfile `FROM` entries and `image` fields in
single-document YAML files. A `FROM` entry that uses a build argument is
reported as `unknown`, even when the argument has a default: `docker build`
can override that default. Explicit version tags are assessed for `php` and
`docker.io/library/php`; digest-pinned references are `unknown` because the
scan doesn't inspect built images or running PHP processes. It also can't
identify source fixes or vendor backports, or account for OS-package fixes. A
clean result is not proof that a deployed system is safe.

## Development

Run the project checks with `composer quality`.

The project is licensed under MIT; see [LICENSE](LICENSE).
