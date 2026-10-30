<?php

declare(strict_types=1);

namespace Tests\Architecture;

final class SliceMap
{
    public const string SHARED = 'Shared';

    public const string APP_NAMESPACE = 'App\\Features';

    public const array PLANNED_SLICES = [
        'Shared', 'Vendor', 'Invoice', 'Extraction', 'Guard', 'Approval', 'Payment',
        'Webhook', 'Telegram', 'AgentGateway', 'Audit', 'Policy', 'Baseline',
    ];

    public const array INTERNAL_NAMESPACES = [
        'Http', 'Models', 'Policies', 'Providers', 'Listeners', 'Jobs', 'Mcp', 'Ai', 'Routes', 'Commands',
        'Notifications', 'Support', 'Filament', 'Tests', 'Console', 'Queries', 'Mail', 'Rules', 'Casts',
        'Observers', 'Scopes', 'Livewire', 'View', 'Middleware', 'Authorization', 'Services', 'Repositories',
        'Concerns', 'Traits', 'Helpers', 'Pipelines', 'Resources', 'Requests', 'Views', 'Database', 'Components',
        'Handlers', 'Channels', 'Broadcasting', 'Mixins', 'Macros', 'Facades',
    ];

    public const array PUBLIC_NAMESPACES = ['Actions', 'Data', 'Enums', 'Events', 'Exceptions', 'Contracts'];

    private const array DEPENDENCIES = [
        'Shared' => [],
        'Vendor' => ['Audit'],
        'Invoice' => ['Extraction', 'Audit'],
        'Extraction' => ['Invoice', 'Guard', 'Audit'],
        'Guard' => ['Vendor', 'Policy', 'Baseline', 'Payment', 'Telegram', 'Audit'],
        'Approval' => ['Invoice', 'Guard', 'Payment', 'Audit'],
        'Payment' => ['Vendor', 'Invoice', 'Telegram', 'Audit'],
        'Webhook' => ['Payment', 'Audit'],
        'Telegram' => ['Approval', 'Audit'],
        'AgentGateway' => ['Invoice', 'Extraction', 'Guard', 'Payment', 'Audit'],
        'Audit' => [],
        'Policy' => ['Audit'],
        'Baseline' => ['Vendor', 'Payment', 'Audit'],
    ];

    /**
     * @param  list<string>|null  $known
     * @return list<string>
     */
    public static function unclassifiedFolders(?string $root = null, ?array $known = null): array
    {
        $root ??= dirname(__DIR__, 2).'/app/Features';
        $known ??= [...self::PUBLIC_NAMESPACES, ...self::INTERNAL_NAMESPACES];
        $folders = glob($root.'/*/*', GLOB_ONLYDIR);
        $unclassified = [];

        foreach ($folders === false ? [] : $folders as $folder) {
            if (! in_array(basename($folder), $known, true)) {
                $unclassified[] = basename(dirname($folder)).'/'.basename($folder);
            }
        }

        sort($unclassified);

        return $unclassified;
    }

    /**
     * @return list<string>
     */
    public static function slices(): array
    {
        return array_keys(self::DEPENDENCIES);
    }

    /**
     * @return list<string>
     */
    public static function allowedDependencies(string $slice): array
    {
        return self::DEPENDENCIES[$slice] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function forbiddenNamespaces(string $slice, string $baseNamespace = self::APP_NAMESPACE): array
    {
        $forbidden = [];

        foreach (self::slices() as $other) {
            if ($other === $slice || $other === self::SHARED) {
                continue;
            }

            if ($slice === self::SHARED) {
                $forbidden[] = $baseNamespace.'\\'.$other;

                continue;
            }

            foreach (self::forbiddenSubNamespaces($slice, $other) as $subNamespace) {
                $forbidden[] = $baseNamespace.'\\'.$other.'\\'.$subNamespace;
            }
        }

        return $forbidden;
    }

    /**
     * @return list<string>
     */
    private static function forbiddenSubNamespaces(string $slice, string $other): array
    {
        if (in_array($other, self::allowedDependencies($slice), true)) {
            return self::INTERNAL_NAMESPACES;
        }

        return [...self::INTERNAL_NAMESPACES, ...self::PUBLIC_NAMESPACES];
    }
}
