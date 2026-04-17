<?php

namespace App\Http\Controllers;

use App\Models\Act;
use App\Models\FkkoCode;
use App\Models\InitialBalance;
use App\Models\JudoJournal;
use App\Services\PolygonModeService;
use App\Services\JournalService;
use App\Services\JournalExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $tenantService = app(\App\Services\TenantService::class);
        $company = $tenantService->getCompany();

        $hasPolygons = PolygonModeService::isEnabled($company);
        $polygons    = $hasPolygons
            ? $company->polygons()->where('status', 'active')->orderBy('name')->get()
            : collect();


        $selectedPolygonId = $hasPolygons ? $request->query('polygon_id') : null;
        $selectedPolygon   = null;

        $journals = collect();
        if ($company) {
            $query = JudoJournal::where('company_id', $company->id)
                ->with(['polygon']);

            if ($selectedPolygonId) {
                $query->where('polygon_id', $selectedPolygonId);
                $selectedPolygon = $polygons->firstWhere('id', $selectedPolygonId);
            }

            $journals = $query->orderBy('period', 'desc')->get();
        }

        $periods = [];
        $now = now();

        $periods[$now->year]       = $now->year . ' год';
        $periods[$now->year - 1]   = ($now->year - 1) . ' год';
        $periods['divider1']       = '---';

        for ($q = 1; $q <= 4; $q++)
            $periods[$now->year . '-Q' . $q] = $q . ' кв. ' . $now->year;
        for ($q = 1; $q <= 4; $q++)
            $periods[($now->year - 1) . '-Q' . $q] = $q . ' кв. ' . ($now->year - 1);
        $periods['divider2'] = '---';

        $current = now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $periods[$current->format('Y-m')] = Str::ucfirst($current->translatedFormat('F Y'));
            $current->subMonth();
        }

        return view('journal.index', compact(
            'journals', 'periods', 'hasPolygons', 'polygons', 'selectedPolygon'
        ));
    }

    public function store(Request $request)
    {
        $company = app(\App\Services\TenantService::class)->getCompany();
        if (!$company) {
            return back()->with('error', 'Компания не выбрана.');
        }

        $hasPolygons = PolygonModeService::isEnabled($company);

        $rules = ['period' => 'required|string'];
        if ($hasPolygons) {
            $rules['polygon_id'] = 'required|exists:polygons,id';
        }
        $request->validate($rules, [
            'polygon_id.required' => 'Выберите полигон для формирования журнала.',
            'polygon_id.exists'   => 'Выбранный полигон не найден.',
        ]);


        $polygonId = null;
        if ($hasPolygons) {
            $polygon = $company->polygons()->findOrFail($request->integer('polygon_id'));
            $polygonId = $polygon->id;
        }

        $roleName = session('user_role', 'Отходообразователь');
        $roleKey = ($roleName === 'Переработчик отходов') ? 'waste_processor' : 'waste_generator';

        $periodInput = trim($request->input('period'));
        $anyJournalExists = JudoJournal::where('company_id', $company->id)->exists();
        $initialBalancesExist = InitialBalance::where('company_id', $company->id)->exists();
        $actsExist = Act::where('company_id', $company->id)->exists();

        if (!$anyJournalExists && !$initialBalancesExist && !$actsExist) {
            $tariffsUrl = route('subscription.index');
            return back()->with('error', "Сначала введите начальные остатки или добавьте акты.<br><br><a href='{$tariffsUrl}' class='btn btn-warning btn-sm fw-bold'>ТАРИФЫ</a>");
        }

        return redirect()->route('journal.initial-balance.create', array_filter([
            'period'     => $periodInput,
            'polygon_id' => $polygonId,
        ]));
    }

    public function createInitialBalance(Request $request) {
        $period = $request->query('period', now()->format('Y-m'));
        $polygonId = $request->query('polygon_id');
        return view('journal.initial_balance', compact('period', 'polygonId'));
    }

    public function storeInitialBalance(Request $request, JournalService $journalService) {
        $request->validate(['period' => 'required', 'wastes' => 'nullable|array']);
        $company = app(\App\Services\TenantService::class)->getCompany();
        if (!$company) abort(404);

        $periodDate = \Carbon\Carbon::parse($request->period);
        foreach ($request->wastes ?? [] as $waste) {
            if (empty($waste['amount']) || $waste['amount'] <= 0) continue;
            InitialBalance::updateOrCreate(
                ['company_id' => $company->id, 'waste_name' => $waste['name'], 'period' => $periodDate->format('Y-m-d')],
                ['fkko_code' => $waste['fkko'], 'hazard_class' => $waste['hazard'], 'amount' => $waste['amount'], 'year' => $periodDate->year]
            );
        }
        $roleKey   = session('user_role') === 'Переработчик отходов' ? 'waste_processor' : 'waste_generator';
        $polygonId = $request->input('polygon_id') ?: null;
        
        try {
            $result = $journalService->generate($company, $request->period, $roleKey, $polygonId);
            return redirect()->route('journal.index')->with('success', 'Журнал сформирован: ' . $result['periodLabel']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignPolygon(Request $request, string $id)
    {
        $company = app(\App\Services\TenantService::class)->getCompany();
        if (!$company) {
            return redirect('/login')->with('error', 'Сессия истекла. Пожалуйста, войдите снова.');
        }
        $journal = JudoJournal::where('company_id', $company->id)->findOrFail($id);

        $polygonId = $request->input('polygon_id');

        if ($polygonId) {

            $company->polygons()->findOrFail($polygonId);
        }

        $journal->polygon_id = $polygonId ?: null;
        $journal->save();

        return back()->with('success', $polygonId
            ? 'Полигон привязан к журналу.'
            : 'Привязка полигона снята.'
        );
    }

    public function show(string $id) {
        $company = app(\App\Services\TenantService::class)->getCompany();
        if (!$company) {
            return redirect('/login')->with('error', 'Сессия истекла. Пожалуйста, войдите снова.');
        }
        $journal = JudoJournal::where('company_id', $company->id)->findOrFail($id);
        $wastes  = FkkoCode::orderBy('name')->get(['name', 'code', 'hazard_class']);

        $hasPolygons = \App\Services\PolygonModeService::isEnabled($company);
        $polygons    = $hasPolygons
            ? $company->polygons()->where('status', 'active')->orderBy('name')->get()
            : collect();

        return view('journal.show', compact('journal', 'wastes', 'hasPolygons', 'polygons'));
    }

    public function update(Request $request, string $id) {
        $journal = JudoJournal::findOrFail($id);
        $data = $journal->{$request->table};
        $data[$request->row_index][$request->column] = $request->value;
        $journal->{$request->table} = $data;
        $journal->save();
        return response()->json(['success' => true]);
    }

    public function destroy(string $id) {
        $company = app(\App\Services\TenantService::class)->getCompany();
        JudoJournal::where('company_id', $company->id)->findOrFail($id)->delete();
        return redirect()->route('journal.index')->with('success', 'Журнал удален.');
    }

    public function download(string $id, JournalExportService $exportService) {
        $user = auth()->user();
        if (!$user) {
            return redirect('/login')->with('error', 'Сессия истекла. Пожалуйста, войдите снова.');
        }
        if (!$user->subscription_ends_at || $user->subscription_ends_at->isPast()) {
            $url = route('subscription.index');
            return back()->with('error', 'Скачивание Excel доступно по подписке. <a href="' . $url . '" class="btn btn-sm btn-light ms-2">Перейти на Тарифы</a>');
        }

        $company = app(\App\Services\TenantService::class)->getCompany();
        $journal = JudoJournal::where('company_id', $company->id)->findOrFail($id);

        $data = $exportService->prepareSpreadsheet($journal, $company);
        $writer = IOFactory::createWriter($data['spreadsheet'], 'Xls');
        return response()->streamDownload(fn() => $writer->save('php://output'), $data['filename']);
    }

    public function downloadPdf(string $id, JournalExportService $exportService) {
        $user = auth()->user();
        if (!$user) {
            return redirect('/login')->with('error', 'Сессия истекла. Пожалуйста, войдите снова.');
        }
        if (!$user->subscription_ends_at || $user->subscription_ends_at->isPast()) {
            $url = route('subscription.index');
            return back()->with('error', 'Скачивание PDF доступно по подписке. <a href="' . $url . '" class="btn btn-sm btn-light ms-2">Перейти на Тарифы</a>');
        }

        $company = app(\App\Services\TenantService::class)->getCompany();
        $journal = JudoJournal::where('company_id', $company->id)->findOrFail($id);

        $data = $exportService->prepareSpreadsheet($journal, $company);
        $filename = str_replace('.xls', '.pdf', $data['filename']);
        
        $writer = IOFactory::createWriter($data['spreadsheet'], 'Mpdf');
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename);
    }
}
