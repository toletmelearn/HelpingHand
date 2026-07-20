<?php

namespace App\Services\Registry;

use App\Contracts\Operations\CheckInterface;

class ErpRegistry
{
    protected array $modules = [];
    protected array $sidebarEntries = [];
    protected array $dashboardWidgets = [];
    protected array $imports = [];
    protected array $exports = [];
    protected array $diagnosticChecks = [];
    protected array $schedulerJobs = [];
    protected array $notificationChannels = [];
    protected array $featureFlags = [];
    protected array $permissions = [];

    /**
     * Register an ERP Module.
     */
    public function registerModule(string $name, array $metadata = []): void
    {
        $this->modules[$name] = array_merge([
            'name' => $name,
            'description' => '',
            'version' => '1.0.0',
            'enabled' => true,
        ], $metadata);
    }

    /**
     * Get all registered modules.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Check if a module is registered and enabled.
     */
    public function isModuleEnabled(string $name): bool
    {
        return isset($this->modules[$name]) && $this->modules[$name]['enabled'];
    }

    /**
     * Register a sidebar menu entry.
     */
    public function registerSidebarEntry(string $sectionKey, array $sectionData): void
    {
        if (!isset($this->sidebarEntries[$sectionKey])) {
            $this->sidebarEntries[$sectionKey] = [
                'title' => $sectionData['title'] ?? ucwords($sectionKey),
                'icon' => $sectionData['icon'] ?? 'bi-box',
                'roles' => $sectionData['roles'] ?? ['admin', 'super-admin'],
                'links' => [],
            ];
        }

        if (isset($sectionData['links'])) {
            foreach ($sectionData['links'] as $link) {
                $this->sidebarEntries[$sectionKey]['links'][] = array_merge([
                    'title' => 'Link',
                    'url' => '#',
                    'icon' => 'bi-circle',
                ], $link);
            }
        }
    }

    /**
     * Get all sidebar entries.
     */
    public function getSidebarEntries(): array
    {
        return $this->sidebarEntries;
    }

    /**
     * Register a dashboard widget.
     */
    public function registerDashboardWidget(array $widget): void
    {
        $this->dashboardWidgets[] = array_merge([
            'id' => uniqid('widget_'),
            'title' => 'Widget',
            'component' => null,
            'roles' => ['admin'],
            'width' => 'col-md-4',
        ], $widget);
    }

    /**
     * Get all dashboard widgets.
     */
    public function getDashboardWidgets(): array
    {
        return $this->dashboardWidgets;
    }

    /**
     * Register an import definition class.
     */
    public function registerImport(string $name, string $class): void
    {
        $this->imports[$name] = $class;
    }

    /**
     * Get all registered import definitions.
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    /**
     * Register an export definition.
     */
    public function registerExport(string $name, string $class): void
    {
        $this->exports[$name] = $class;
    }

    /**
     * Get all registered export definitions.
     */
    public function getExports(): array
    {
        return $this->exports;
    }

    /**
     * Register a diagnostic check.
     */
    public function registerDiagnosticCheck(CheckInterface $check): void
    {
        $this->diagnosticChecks[] = $check;
    }

    /**
     * Get all registered diagnostic checks.
     */
    public function getDiagnosticChecks(): array
    {
        return $this->diagnosticChecks;
    }

    /**
     * Register a scheduler job metadata.
     */
    public function registerSchedulerJob(array $job): void
    {
        $this->schedulerJobs[] = array_merge([
            'command' => '',
            'description' => '',
            'frequency' => 'daily',
            'is_active' => true,
        ], $job);
    }

    /**
     * Get all registered scheduler jobs.
     */
    public function getSchedulerJobs(): array
    {
        return $this->schedulerJobs;
    }

    /**
     * Register a notification channel.
     */
    public function registerNotificationChannel(string $name, array $metadata = []): void
    {
        $this->notificationChannels[$name] = array_merge([
            'name' => $name,
            'enabled' => true,
            'description' => '',
        ], $metadata);
    }

    /**
     * Get all registered notification channels.
     */
    public function getNotificationChannels(): array
    {
        return $this->notificationChannels;
    }

    /**
     * Register a feature flag.
     */
    public function registerFeatureFlag(string $flag, bool $default = false): void
    {
        if (!isset($this->featureFlags[$flag])) {
            $this->featureFlags[$flag] = $default;
        }
    }

    /**
     * Get feature flag value.
     */
    public function getFeatureFlag(string $flag): bool
    {
        return $this->featureFlags[$flag] ?? false;
    }

    /**
     * Set feature flag value dynamically.
     */
    public function setFeatureFlag(string $flag, bool $value): void
    {
        $this->featureFlags[$flag] = $value;
    }

    /**
     * Get all feature flags.
     */
    public function getFeatureFlags(): array
    {
        return $this->featureFlags;
    }

    /**
     * Register system permissions.
     */
    public function registerPermission(string $permission, string $description = ''): void
    {
        $this->permissions[$permission] = $description;
    }

    /**
     * Get all registered permissions.
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
