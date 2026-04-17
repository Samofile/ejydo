<?php

namespace App\Services;

use App\Models\Act;
use App\Models\FkkoCode;
use App\Models\InitialBalance;
use App\Models\JudoJournal;
use App\Models\UserCompany;
use Carbon\Carbon;

class JournalService
{
    public function generate(UserCompany $company, string $periodInput, string $roleKey, ?int $polygonId = null)
    {
        $startDate = null;
        $endDate = null;
        $type = 'month';
        $periodLabel = $periodInput;

        try {
            if (strlen($periodInput) === 4 && is_numeric($periodInput)) {
                $type = 'year';
                $startDate = Carbon::createFromDate((int) $periodInput, 1, 1)->startOfDay();
                $endDate = $startDate->copy()->endOfYear();
                $periodLabel = $periodInput . ' год';
            } elseif (str_contains($periodInput, '-Q')) {
                $type = 'quarter';
                $parts = explode('-Q', $periodInput);
                $year = (int) $parts[0];
                $quarter = (int) $parts[1];
                $startMonth = ($quarter - 1) * 3 + 1;
                $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
                $endDate = $startDate->copy()->addMonths(2)->endOfMonth();
                $periodLabel = $quarter . ' квартал ' . $year;
            } else {
                $type = 'month';
                if (!preg_match('/^\d{4}-\d{2}$/', $periodInput)) {
                    throw new \Exception("Формат Y-m ожидался, получено: $periodInput");
                }
                $startDate = Carbon::createFromFormat('Y-m', $periodInput)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $periodLabel = $startDate->translatedFormat('F Y');
            }
        } catch (\Exception $e) {
            throw new \Exception('Неверный формат периода: ' . $periodInput);
        }

        $prevJournal = JudoJournal::where('company_id', $company->id)
            ->where('period', '<', $startDate->format('Y-m-d'))
            ->where('role', $roleKey)
            ->orderBy('period', 'desc')
            ->first();

        $wasteStats = [];

        if (!$prevJournal) {
            $initials = InitialBalance::where('company_id', $company->id)->get();
            foreach ($initials as $init) {
                $f_code = \App\Models\FkkoCode::formatCode($init->fkko_code);
                $wasteStats[$init->waste_name] = $this->emptyStats($f_code, $init->hazard_class);
                $wasteStats[$init->waste_name]['start_accumulation'] = (float) $init->amount;
                $wasteStats[$init->waste_name]['start_storage'] = (float) ($init->storage_amount ?? 0);
            }
        } else {
            foreach ($prevJournal->table2_data as $item) {
                $f_code = \App\Models\FkkoCode::formatCode($item['fkko'] ?? '');
                $wasteStats[$item['name']] = $this->emptyStats($f_code, $item['hazard'] ?? '');
                $wasteStats[$item['name']]['start_accumulation'] = (float) ($item['end_accumulation'] ?? $item['balance_end'] ?? 0);
                $wasteStats[$item['name']]['start_storage'] = (float) ($item['end_storage'] ?? 0);
            }
        }

        $acts = Act::where('company_id', $company->id)
            ->where('status', 'processed')
            ->get()
            ->filter(function ($act) use ($startDate, $endDate) {
                $dateVal = $act->act_data['date'] ?? null;
                $actDate = $dateVal ? Carbon::parse($dateVal) : $act->created_at;
                return $actDate->between($startDate, $endDate);
            });

        $table3_data = [];
        $table4_data = [];

        foreach ($acts as $act) {
            $data = $act->act_data;
            $items = $data['items'] ?? [];
            $compInn = trim($company->inn);
            $pInn = trim($data['provider_snapshot']['inn'] ?? '');
            $rInn = trim($data['receiver_snapshot']['inn'] ?? '');

            $isWasteGenerator = ($pInn === $compInn);
            $isWasteRecipient = ($rInn === $compInn);

            if (empty($compInn) || (empty($pInn) && empty($rInn))) {
                $compName = mb_strtolower($company->name);
                $provider = mb_strtolower($data['provider'] ?? '');
                $receiver = mb_strtolower($data['receiver'] ?? '');
                $isWasteGenerator = (str_contains($provider, $compName));
                $isWasteRecipient = (str_contains($receiver, $compName));
            }

            $isInternal = ($isWasteRecipient && $isWasteGenerator);
            $actType = $act->act_type ?? 'transfer';

            foreach ($items as $item) {
                $name = $item['name'] ?? 'Unknown';
                $qty = (float) ($item['quantity'] ?? 0);

                $fkko = \App\Models\FkkoCode::formatCode($item['fkko_code'] ?? '');
                $hazard = $item['hazard_class'] ?? '';

                $opItemOriginal = $item['operation_type'] ?? '';
                $opArr = array_filter(array_map('trim', explode(',', $opItemOriginal)), function($op) {
                    return mb_strtolower($op) !== 'транспортирование';
                });
                $opItem = mb_strtolower(implode(', ', $opArr));

                $itemIsGenerator = $isWasteGenerator;
                $itemIsRecipient = $isWasteRecipient;
                $itemIsInternal  = $isInternal;
                
                if ($actType === 'transfer' && str_contains($opItem, 'третьим лицам')) {
                    $itemIsGenerator = true;
                    $itemIsRecipient = false;
                    $itemIsInternal  = false;
                }

                if ($actType === 'third_party') {
                    $itemIsGenerator = true;
                    $itemIsRecipient = false;
                    $itemIsInternal  = false;
                }

                if (empty($opArr) && empty($actType)) {
                    continue;
                }

                if (!isset($wasteStats[$name])) {
                    $wasteStats[$name] = $this->emptyStats($fkko, $hazard);
                }

                if (str_contains($opItem, 'образован')) {
                    $wasteStats[$name]['generated'] += $qty;
                }

                if ($itemIsInternal) {
                    if ($actType === 'processing') $wasteStats[$name]['processed'] += $qty;
                    elseif ($actType === 'utilization') $wasteStats[$name]['utilized'] += $qty;
                    elseif ($actType === 'neutralization') $wasteStats[$name]['neutralized'] += $qty;
                    elseif ($actType === 'storage') $wasteStats[$name]['stored'] += $qty;
                    elseif ($actType === 'burial') $wasteStats[$name]['buried'] += $qty;
                } else {
                    if ($itemIsGenerator && !$itemIsInternal) {
                        $wasteStats[$name]['transferred_total'] += $qty;

                        if ($actType === 'processing' || str_contains($opItem, 'обработ')) 
                            $wasteStats[$name]['trans_process'] += $qty;
                        elseif ($actType === 'utilization' || str_contains($opItem, 'утилиз')) 
                            $wasteStats[$name]['trans_util'] += $qty;
                        elseif ($actType === 'neutralization' || str_contains($opItem, 'обезвреж')) 
                            $wasteStats[$name]['trans_neutr'] += $qty;
                        elseif ($actType === 'storage' || str_contains($opItem, 'хран')) 
                            $wasteStats[$name]['trans_store'] += $qty;
                        elseif ($actType === 'burial' || str_contains($opItem, 'захорон')) 
                            $wasteStats[$name]['trans_bury'] += $qty;
                    }

                    if ($itemIsRecipient) {
                        if ($actType === 'transfer') {
                            $wasteStats[$name]['received'] += $qty;
                        }
                        if ($actType === 'processing') $wasteStats[$name]['processed'] += $qty;
                        elseif ($actType === 'utilization') $wasteStats[$name]['utilized'] += $qty;
                        elseif ($actType === 'neutralization') $wasteStats[$name]['neutralized'] += $qty;
                        elseif ($actType === 'storage') $wasteStats[$name]['stored'] += $qty;
                        elseif ($actType === 'burial') $wasteStats[$name]['buried'] += $qty;
                    }
                }

                if ($itemIsGenerator && !$itemIsInternal && in_array($actType, ['transfer', 'third_party'])) {
                    $recipientLabel = $data['receiver'] ?? '';
                    $recipientLicense = '';
                    
                    if (!empty($data['receiver_snapshot'])) {
                        $rs = is_array($data['receiver_snapshot']) ? $data['receiver_snapshot'] : json_decode($data['receiver_snapshot'], true);
                        if ($rs) {
                            $recipientLabel = $rs['name'] . (!empty($rs['inn']) ? " (ИНН: {$rs['inn']})" : "");
                            $lic = trim($rs['license_number'] ?? '');
                            if (!empty($lic)) {
                                $isPerpetual = !empty($rs['license_perpetual']) && $rs['license_perpetual'] === true;
                                $validUntil = $rs['license_valid_until'] ?? '';
                                if ($isPerpetual || str_contains(mb_strtolower((string)$validUntil), 'бессрочная')) {
                                    $lic .= " (бессрочная)";
                                } elseif (!empty($validUntil)) {
                                    try {
                                        $lic .= " (до " . \Carbon\Carbon::parse($validUntil)->format('d.m.Y') . ")";
                                    } catch (\Exception $e) {
                                        $lic .= " (до " . $validUntil . ")";
                                    }
                                }
                            }
                            $recipientLicense = $lic;
                        }
                    }

                    $table3_data[] = [
                        'date'              => $data['date'] ?? '',
                        'number'            => $act->act_number,
                        'counterparty'      => $recipientLabel,
                        'waste'             => $name,
                        'fkko'              => $fkko,
                        'hazard'            => $hazard,
                        'amount'            => $qty,
                        'operation'         => $opItem,
                        'amt_process'       => str_contains($opItem, 'обработ') ? $qty : 0,
                        'amt_util'          => str_contains($opItem, 'утилиз') ? $qty : 0,
                        'amt_neutr'         => str_contains($opItem, 'обезвреж') ? $qty : 0,
                        'amt_store'         => str_contains($opItem, 'хран') ? $qty : 0,
                        'amt_bury'          => str_contains($opItem, 'захорон') ? $qty : 0,
                        'contract_details'  => $data['contract_details'] ?? '',
                        'contract_validity' => $data['contract_validity'] ?? '',
                        'license'           => $recipientLicense,
                    ];
                }

                if ($itemIsRecipient && !$itemIsInternal) {
                    $providerLabel = $data['provider'] ?? '';
                    $providerLicense = $company->license_details ?? '';
    
                    if (!empty($data['provider_snapshot'])) {
                        $ps = is_array($data['provider_snapshot']) ? $data['provider_snapshot'] : json_decode($data['provider_snapshot'], true);
                        if ($ps) {
                            $providerLabel = $ps['name'] . (!empty($ps['inn']) ? " (ИНН: {$ps['inn']})" : "");
                            $providerLicense = $ps['license_number'] ?? $providerLicense;
                        }
                    }

                    $table4_data[] = [
                        'date'              => $data['date'] ?? '',
                        'number'            => $act->act_number,
                        'counterparty'      => $providerLabel,
                        'waste'             => $name,
                        'fkko'              => $fkko,
                        'hazard'            => $hazard,
                        'amount'            => $qty,
                        'operation'         => $opItem,
                        'amt_third_party'   => str_contains($opItem, 'третьим лицам') ? $qty : 0,
                        'amt_process'       => str_contains($opItem, 'обработ') ? $qty : 0,
                        'amt_util'          => str_contains($opItem, 'утилиз') ? $qty : 0,
                        'amt_neutr'         => str_contains($opItem, 'обезвреж') ? $qty : 0,
                        'amt_store'         => str_contains($opItem, 'хран') ? $qty : 0,
                        'amt_bury'          => str_contains($opItem, 'захорон') ? $qty : 0,
                        'contract_details'  => $data['contract_details'] ?? '',
                        'contract_validity' => $data['contract_validity'] ?? '',
                    ];
                }
            }
        }

        $fkkoCatalog = FkkoCode::whereNotNull('code')
            ->get(['code', 'origin', 'aggregate_state', 'chemical_composition'])
            ->keyBy(fn($f) => preg_replace('/\s+/', '', $f->code));

        $table2 = [];
        foreach ($wasteStats as $wasteName => $s) {
            $endStorage = $s['start_storage'] + $s['stored'];
            $placedTotal = $s['stored'] + $s['buried'];

            $endAccumulation = ($s['start_accumulation']
                + $s['generated']
                + $s['received'])
                - ($s['processed']
                    + $s['utilized']
                    + $s['neutralized']
                    + $s['transferred_total'])
                + $placedTotal;

            $table2[] = [
                'name' => $wasteName,
                'fkko' => $s['fkko'],
                'hazard' => $s['hazard'],
                'start_storage' => $s['start_storage'],
                'start_accumulation' => $s['start_accumulation'],
                'balance_begin' => $s['start_accumulation'],
                'generated' => $s['generated'],
                'received' => $s['received'],
                'processed' => $s['processed'],
                'utilized' => $s['utilized'],
                'neutralized' => $s['neutralized'],
                'transferred_total' => $s['transferred_total'],
                'trans_process' => $s['trans_process'] ?? 0,
                'trans_util' => $s['trans_util'] ?? 0,
                'trans_neutr' => $s['trans_neutr'] ?? 0,
                'trans_store' => $s['trans_store'] ?? 0,
                'trans_bury' => $s['trans_bury'] ?? 0,
                'placed_total' => $placedTotal,
                'stored' => $s['stored'],
                'buried' => $s['buried'],
                'end_storage' => $endStorage,
                'end_accumulation' => $endAccumulation,
                'balance_end' => $endAccumulation
            ];
        }

        $journal = JudoJournal::updateOrCreate(
            [
                'company_id' => $company->id,
                'period'     => $startDate->format('Y-m-d'),
                'type'       => $type,
                'role'       => $roleKey,
                'polygon_id' => $polygonId,
            ],
            [
                'table1_data' => array_values(collect($table2)->map(function ($x) use ($fkkoCatalog) {
                    $codeKey = preg_replace('/\s+/', '', $x['fkko'] ?? '');
                    $fkkoRef = $fkkoCatalog->get($codeKey);
                    return [
                        'name'                 => $x['name'],
                        'fkko'                 => $x['fkko'],
                        'hazard'               => $x['hazard'],
                        'origin'               => $fkkoRef?->origin ?? '-',
                        'aggregate_state'      => $fkkoRef?->aggregate_state ?? '-',
                        'chemical_composition' => $fkkoRef?->chemical_composition ?? '-',
                    ];
                })->toArray()),
                'table2_data' => $table2,
                'table3_data' => $table3_data,
                'table4_data' => $table4_data,
            ]
        );

        return [
            'journal' => $journal,
            'periodLabel' => $periodLabel
        ];
    }

    private function emptyStats($fkko = '', $hazard = '') {
        return [
            'start_storage' => 0,
            'start_accumulation' => 0,
            'generated' => 0,
            'received' => 0,
            'processed' => 0,
            'utilized' => 0,
            'neutralized' => 0,
            'transferred_total' => 0,
            'trans_process' => 0, 'trans_util' => 0, 'trans_neutr' => 0, 'trans_store' => 0, 'trans_bury' => 0,
            'stored' => 0,
            'buried' => 0,
            'fkko' => $fkko,
            'hazard' => $hazard
        ];
    }
}
