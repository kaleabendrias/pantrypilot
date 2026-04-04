<?php
declare(strict_types=1);

namespace app\service;

use app\repository\ReportingRepository;

final class ReportingService
{
    public function __construct(private readonly ReportingRepository $reportingRepository)
    {
    }

    public function dashboard(array $scopes = [], array $authUser = []): array
    {
        return $this->reportingRepository->kpis($scopes, $authUser);
    }

    public function anomalies(array $scopes = [], array $authUser = []): array
    {
        return $this->reportingRepository->anomalyMetrics($scopes, $authUser);
    }

    public function generateAlerts(array $scopes = [], array $authUser = []): array
    {
        $metrics = $this->reportingRepository->anomalyMetrics($scopes, $authUser);
        $persisted = $this->reportingRepository->persistAlerts($metrics['alerts'] ?? []);
        return ['alerts_generated' => $persisted, 'metrics' => $metrics];
    }

    public function exportBookingsCsv(array $scopes = [], array $authUser = []): array
    {
        $csv = $this->reportingRepository->exportBookingsCsv($scopes, $authUser);
        return [
            'filename' => 'bookings_' . date('Ymd_His') . '.csv',
            'content_base64' => base64_encode($csv),
        ];
    }
}
