<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Lead\Repositories\PipelineRepository;

class DashboardController extends Controller
{
    use PDFHandler;

    /**
     * Request param functions
     *
     * @var array
     */
    protected $typeFunctions = [
        'over-all' => 'getOverAllStats',
        'revenue-stats' => 'getRevenueStats',
        'total-leads' => 'getTotalLeadsStats',
        'revenue-by-sources' => 'getLeadsStatsBySources',
        'revenue-by-types' => 'getLeadsStatsByTypes',
        'top-selling-products' => 'getTopSellingProducts',
        'top-persons' => 'getTopPersons',
        'open-leads-by-states' => 'getOpenLeadsByStates',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected PipelineRepository $pipelineRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate' => $this->dashboardHelper->getEndDate(),
            'pipelines' => $this->pipelineRepository->all(),
            'defaultPipeline' => $this->pipelineRepository->getDefaultPipeline(),
            'operationalStats' => $this->getOperationalStats(),
        ]);
    }

    private function getOperationalStats(): array
    {
        if (bouncer()->getAuthorizedUserIds() !== null) {
            return [];
        }

        $today = now()->startOfDay();
        $nextThirtyDays = $today->copy()->addDays(30);
        $stats = [];

        if (bouncer()->hasPermission('human-resources.employees')) {
            $employees = DB::table('hr_employees')->whereNull('deleted_at');
            $stats['employees'] = $employees->count();
            $stats['active_employees'] = $employees->where('status', 'active')->count();
        }

        if (bouncer()->hasPermission('human-resources.contracts')) {
            $contracts = DB::table('hr_service_contracts')->whereNull('deleted_at')->where('status', 'active');
            $stats['active_contracts'] = $contracts->count();
            $stats['monthly_contract_value'] = $contracts->sum('monthly_value');
            $stats['contracts_expiring'] = $contracts->whereBetween('ends_at', [$today, $nextThirtyDays])->count();
        }

        if (isset($stats['monthly_contract_value']) && bouncer()->hasPermission('human-resources.departments')) {
            $stats['departments'] = DB::table('hr_departments')->where('is_active', true)->count();
        }

        $private = bouncer()->hasPermission('human-resources.document-center');
        $shared = bouncer()->hasPermission('human-resources.shared-documents');

        if (bouncer()->hasPermission('document-center') && ($private || $shared)) {
            $stats['documents_expiring'] = DB::table('hr_employee_documents')->whereBetween('expires_at', [$today, $nextThirtyDays])
                ->when(! $private, fn ($query) => $query->whereNull('employee_id'))
                ->when(! $shared, fn ($query) => $query->whereNotNull('employee_id'))->count();
        }

        if (bouncer()->hasPermission('human-resources.incidents')) {
            $stats['open_incidents'] = DB::table('hr_incidents')->whereNull('deleted_at')->whereIn('status', ['open', 'investigating'])->count();
        }

        return $stats;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function stats()
    {
        $stats = $this->dashboardHelper->{$this->typeFunctions[request()->query('type')]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }

    /**
     * Export the dashboard report to PDF for the currently applied filters.
     */
    public function exportPdf(): Response|StreamedResponse
    {
        $revenueStats = $this->dashboardHelper->getRevenueStats();

        $overAllStats = $this->dashboardHelper->getOverAllStats();

        $totalLeadsStats = $this->dashboardHelper->getTotalLeadsStats();

        $revenueBySources = $this->dashboardHelper->getLeadsStatsBySources();

        $revenueByTypes = $this->dashboardHelper->getLeadsStatsByTypes();

        $openLeadsByStates = $this->dashboardHelper->getOpenLeadsByStates();

        $topSellingProducts = $this->dashboardHelper->getTopSellingProducts();

        $topPersons = $this->dashboardHelper->getTopPersons();

        return $this->downloadPDF(
            view('admin::dashboard.pdf', [
                'logo' => $this->getLogoDataUri(),
                'startDate' => $this->dashboardHelper->getStartDate(),
                'endDate' => $this->dashboardHelper->getEndDate(),
                'revenueStats' => $revenueStats,
                'overAllStats' => $overAllStats,
                'totalLeadsStats' => $totalLeadsStats,
                'revenueBySources' => $revenueBySources,
                'revenueByTypes' => $revenueByTypes,
                'openLeadsByStates' => $openLeadsByStates,
                'topSellingProducts' => $topSellingProducts,
                'topPersons' => $topPersons,
            ])->render(),
            'Dashboard_Report_'.now()->format('d-m-Y')
        );
    }

    /**
     * Resolve the configured admin logo as a base64 data URI so dompdf can
     * render it regardless of the storage disk or symlink configuration.
     */
    private function getLogoDataUri(): ?string
    {
        $path = core()->getConfigData('general.general.admin_logo.logo_image');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($path);

        $contents = base64_encode(Storage::disk('public')->get($path));

        return "data:{$mimeType};base64,{$contents}";
    }
}
