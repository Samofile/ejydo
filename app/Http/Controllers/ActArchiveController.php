<?php

namespace App\Http\Controllers;

use App\Models\Act;
use Illuminate\Http\Request;

class ActArchiveController extends Controller
{
    public function index(Request $request)
    {
        $tenantService = app(\App\Services\TenantService::class);
        $company = $tenantService->getCompany();

        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Пожалуйста, выберите компанию');
        }

        $query = Act::where('company_id', $company->id)
            ->where('status', 'processed');


        if ($request->filled('act_type')) {
            $query->where('act_type', $request->act_type);
        }


        if ($request->filled('period_year')) {
            $year = $request->period_year;
            if ($request->filled('period_quarter')) {

                $q = (int) $request->period_quarter;
                $monthFrom = ($q - 1) * 3 + 1;
                $monthTo   = $monthFrom + 2;
                $query->whereRaw(
                    "YEAR(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) = ?
                     AND MONTH(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) BETWEEN ? AND ?",
                    [$year, $monthFrom, $monthTo]
                );
            } elseif ($request->filled('period_month')) {
                $query->whereRaw(
                    "YEAR(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) = ?
                     AND MONTH(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) = ?",
                    [$year, $request->period_month]
                );
            } else {
                $query->whereRaw(
                    "YEAR(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) = ?",
                    [$year]
                );
            }
        }

        $acts = $query
            ->orderByDesc(\DB::raw("JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))"))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();


        $availableYears = Act::where('company_id', $company->id)
            ->where('status', 'processed')
            ->selectRaw("DISTINCT YEAR(JSON_UNQUOTE(JSON_EXTRACT(act_data, '$.date'))) as yr")
            ->orderByDesc('yr')
            ->pluck('yr')
            ->filter()
            ->values();

        return view('acts.archive', compact('acts', 'company', 'availableYears'));
    }

    public function update(Request $request, Act $act)
    {
        $tenantService = app(\App\Services\TenantService::class);
        $company = $tenantService->getCompany();

        if (!$company || $act->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'field' => 'required|string',
            'value' => 'required'
        ]);

        $actData = $act->act_data;
        $field   = $request->field;
        $value   = $request->value;

        if (in_array($field, ['date', 'contract_details', 'provider', 'receiver'])) {
            $actData[$field] = $value;
        } elseif (str_starts_with($field, 'items.')) {
            $parts     = explode('.', $field);
            $index     = (int) $parts[1];
            $itemField = $parts[2];
            if (isset($actData['items'][$index])) {
                $actData['items'][$index][$itemField] = $value;
            }
        }

        $act->act_data          = $actData;
        $act->processing_result = $actData;
        $act->save();

        return response()->json(['success' => true, 'message' => 'Данные обновлены']);
    }
    public function edit(Act $act)
    {
        $tenantService = app(\App\Services\TenantService::class);
        $company = $tenantService->getCompany();
        if (!$company || $act->company_id !== $company->id) abort(403);

        $actData  = $act->act_data;
        $actTypes = \App\Models\Act::TYPES;
        $nextNumber = $act->act_number;
        $currentCompany = $company;
        $actType  = $act->act_type;

        return view('acts.edit', compact('act', 'actData', 'actTypes', 'actType', 'currentCompany', 'nextNumber'));
    }

    public function fullUpdate(Request $request, Act $act)
    {
        $tenantService = app(\App\Services\TenantService::class);
        $company = $tenantService->getCompany();
        if (!$company || $act->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $noProviderTypes = ['processing', 'utilization', 'neutralization'];
        $requiresProvider = !in_array($act->act_type, $noProviderTypes);

        $request->validate([
            'date'                => 'required|date',
            'contract_details'    => 'nullable|string|max:500',
            'contract_validity'   => 'nullable|string|max:255',
            'provider'            => $requiresProvider ? 'required|string|max:255' : 'nullable|string|max:255',
            'provider_snapshot'   => 'nullable|string',
            'receiver'            => 'required|string|max:255',
            'receiver_snapshot'   => 'nullable|string',
            'wastes'              => 'required|array|min:1',
            'wastes.*.name'       => 'required|string',
            'wastes.*.fkko_code'  => 'required|string',
            'wastes.*.hazard_class'   => 'required|string',
            'wastes.*.amount'         => 'required|numeric|min:0',
            'wastes.*.operation_types'=> 'required|string',
        ]);

        $pSnap = $request->provider_snapshot ? json_decode($request->provider_snapshot, true) : null;
        $rSnap = $request->receiver_snapshot ? json_decode($request->receiver_snapshot, true) : null;

        if (!$pSnap) $pSnap = ['name' => $request->provider];
        if (!$rSnap) $rSnap = ['name' => $request->receiver];

        $items = [];
        foreach ($request->wastes as $waste) {
            $items[] = [
                'name'           => $waste['name'],
                'quantity'       => (float) $waste['amount'],
                'unit'           => 'т',
                'fkko_code'      => $waste['fkko_code'],
                'hazard_class'   => $waste['hazard_class'],
                'operation_type' => $waste['operation_types'],
            ];
        }

        $actData = [
            'date'              => $request->date,
            'contract_details'  => $request->contract_details,
            'contract_validity' => $request->contract_validity,
            'provider'          => $request->provider,
            'provider_snapshot' => $pSnap,
            'receiver'          => $request->receiver,
            'receiver_snapshot' => $rSnap,
            'items'             => $items,
        ];

        $act->act_data          = $actData;
        $act->processing_result = $actData;
        $act->contract_details  = $request->contract_details;
        $act->save();

        return redirect()->route('acts.archive')->with('success', "Акт №{$act->act_number} успешно обновлён");
    }
}
