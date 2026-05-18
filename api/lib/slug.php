<?php
declare(strict_types=1);

/** Slug normalization and validation. Reserved-words list is in the DB so admins can edit it. */

function normalize_slug(string $raw): string
{
    $s = strtolower(trim($raw));
    $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
    $s = preg_replace('/-+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if (strlen($s) > 40) $s = substr($s, 0, 40);
    $s = trim($s, '-');
    return $s;
}

function slug_format_validity(string $slug): array
{
    if ($slug === '') return [false, 'empty slug'];
    if (strlen($slug) < 3) return [false, 'slug must be at least 3 characters'];
    if (strlen($slug) > 40) return [false, 'slug too long (max 40)'];
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        return [false, 'only letters, digits and hyphens allowed; cannot start or end with hyphen'];
    }
    return [true, null];
}

function slug_is_reserved(array $CONFIG, string $slug): bool
{
    $stmt = db($CONFIG)->prepare('SELECT 1 FROM reserved_slugs WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return (bool)$stmt->fetchColumn();
}

function slug_validity(array $CONFIG, string $slug): array
{
    [$ok, $err] = slug_format_validity($slug);
    if (!$ok) return [false, $err];
    if (slug_is_reserved($CONFIG, $slug)) return [false, 'this subdomain is reserved by the admin'];
    return [true, null];
}
