@extends('layouts.app')

@push('styles')
    <style>
        #waste-search-section { display: none; }
        .act-type-btn {
            border: 2px solid #dee2e6; background: white; border-radius: 8px;
            padding: 12px 16px; cursor: default; transition: all 0.2s;
            text-align: center; font-size: 0.875rem;
        }
        .act-type-btn.selected {
            border-color: #FF4C2B; background-color: rgba(255,76,43,0.08);
            color: #FF4C2B; font-weight: 600;
        }
        .act-type-btn i { display: block; font-size: 1.5rem; margin-bottom: 4px; }
        .waste-item-card { background: #f8f9fa; border-radius: 8px; padding: 12px 16px; margin-bottom: 10px; position: relative; }
        .waste-item-card .remove-waste { position: absolute; top: 8px; right: 8px; cursor: pointer; }
    </style>
@endpush

@section('content')
@php
    $pSnap = is_array($actData['provider_snapshot'] ?? null)
        ? $actData['provider_snapshot']
        : json_decode($actData['provider_snapshot'] ?? '{}', true);
    $rSnap = is_array($actData['receiver_snapshot'] ?? null)
        ? $actData['receiver_snapshot']
        : json_decode($actData['receiver_snapshot'] ?? '{}', true);
    $hideProviderContract = in_array($actType, ['processing', 'utilization', 'neutralization']);
    $actTypeIcons = [
        'transfer'       => 'bi-box-arrow-in-down',
        'third_party'    => 'bi-box-arrow-up-right',
        'processing'     => 'bi-gear',
        'utilization'    => 'bi-recycle',
        'neutralization' => 'bi-shield-check',
        'storage'        => 'bi-box-seam',
        'burial'         => 'bi-archive',
    ];
    $actOperationMap = [
        'processing'     => ['id' => 'temp-op4', 'label' => 'Обработка'],
        'utilization'    => ['id' => 'temp-op2', 'label' => 'Утилизация'],
        'neutralization' => ['id' => 'temp-op3', 'label' => 'Обезвреживание'],
    ];
    $presetOp = $actOperationMap[$actType] ?? null;
    $thirdPartyOps = $actType === 'third_party' ? [
        'temp-op4' => 'Обработка',
        'temp-op2' => 'Утилизация',
        'temp-op3' => 'Обезвреживание',
        'temp-op6' => 'Размещение (Хранение)',
        'temp-op5' => 'Размещение (Захоронение)',
        'temp-op9' => 'Транспортирование',
    ] : [];
    $operations = [
        'temp-op2' => 'Утилизация',
        'temp-op3' => 'Обезвреживание',
        'temp-op4' => 'Обработка',
        'temp-op5' => 'Размещение (Захоронение)',
        'temp-op6' => 'Размещение (Хранение)',
        'temp-op7' => 'Накопление',
        'temp-op9' => 'Транспортирование',
    ];
@endphp
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-9">


                <div class="d-flex align-items-center gap-3 mb-4">
                    <a href="{{ route('acts.archive') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Реестр
                    </a>
                    <h4 class="fw-bold mb-0">Редактирование акта № {{ $act->act_number }}</h4>
                    <span class="badge bg-danger fs-6">{{ $actTypes[$actType] ?? 'Акт' }}</span>
                </div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif


                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <label class="form-label text-muted small text-uppercase fw-bold mb-3">Тип акта</label>
                        <div class="row g-2 mb-0">
                            @foreach($actTypes as $typeKey => $typeLabel)
                                <div class="col-md-4 col-6">
                                    <div class="act-type-btn {{ $actType === $typeKey ? 'selected' : 'text-secondary' }}">
                                        <i class="bi {{ $actTypeIcons[$typeKey] ?? 'bi-file-earmark-text' }}"></i>
                                        {{ $typeLabel }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex align-items-center gap-2">
                        <span class="badge bg-danger fs-6">{{ $actTypes[$actType] ?? 'Акт' }}</span>
                        <span class="text-muted small">{{ $currentCompany->name }}</span>
                    </div>
                    <div class="card-body p-4">

                        <form action="{{ route('acts.archive.full-update', $act->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="act_type" value="{{ $actType }}">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small text-uppercase fw-bold">Номер акта</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">№</span>
                                        <input type="text" class="form-control bg-light fw-bold" value="{{ $act->act_number }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Дата акта</label>
                                    <input type="date" name="date" class="form-control"
                                        value="{{ $actData['date'] ?? '' }}" required>
                                </div>
                            </div>

                            @if(!$hideProviderContract)
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Номер договора, дата</label>
                                    <input type="text" name="contract_details" class="form-control"
                                        value="{{ $actData['contract_details'] ?? '' }}"
                                        placeholder="Например: Договор №5 от 01.01.2026">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Срок действия договора</label>
                                    <input type="text" name="contract_validity" class="form-control"
                                        value="{{ $actData['contract_validity'] ?? '' }}"
                                        placeholder="Например: до 31.12.2026 или бессрочный">
                                </div>
                            </div>
                            @endif

                            <input type="hidden" id="current-company-info" value="{{ json_encode([
                                'name' => $currentCompany->name,
                                'inn' => $currentCompany->inn,
                                'kpp' => $currentCompany->kpp,
                                'legal_address' => $currentCompany->legal_address,
                                'phone' => $currentCompany->phone,
                                'license_number' => $currentCompany->license_details,
                                'license_valid_until' => $currentCompany->license_valid_until,
                            ]) }}">


                            <input type="hidden" id="prefill-provider-snap" value="{{ json_encode($pSnap) }}">
                            <input type="hidden" id="prefill-receiver-snap" value="{{ json_encode($rSnap) }}">
                            <input type="hidden" id="prefill-provider-name" value="{{ $actData['provider'] ?? '' }}">
                            <input type="hidden" id="prefill-receiver-name" value="{{ $actData['receiver'] ?? '' }}">

                            <div class="row align-items-start">

                                @if(!$hideProviderContract)
                                <div class="col-md-5 mb-3">
                                    <label class="form-label fw-medium">Поставщик <span class="text-danger">*</span></label>
                                    <div class="counterparty-widget" id="provider-widget">
                                        <div class="input-group">
                                            <input type="text" class="form-control cp-search" id="provider-search"
                                                placeholder="Введите название или ИНН..." autocomplete="off">
                                            <button class="btn btn-outline-secondary cp-add-btn" type="button"
                                                data-target="provider" title="Добавить в справочник">
                                                <i class="bi bi-person-plus"></i>
                                            </button>
                                        </div>
                                        <div class="list-group position-absolute shadow cp-results" id="provider-results"
                                            style="display:none; z-index:1050; width:100%; max-height:220px; overflow-y:auto;"></div>
                                        <input type="hidden" name="provider" id="provider-value" required>
                                        <input type="hidden" name="provider_snapshot" id="provider-snapshot">
                                        <div class="cp-details card mt-2 border-0 bg-light p-2" id="provider-details" style="display:none; font-size:0.85rem;">
                                            <div class="row g-1">
                                                <div class="col-4"><span class="text-muted">ИНН:</span> <span class="fw-medium cp-inn"></span></div>
                                                <div class="col-4"><span class="text-muted">КПП:</span> <span class="fw-medium cp-kpp"></span></div>
                                                <div class="col-4"><span class="text-muted">ОГРН:</span> <span class="fw-medium cp-ogrn"></span></div>
                                                <div class="col-12"><span class="text-muted">Адрес:</span> <span class="cp-addr"></span></div>
                                                <div class="col-12"><span class="text-muted">Тел:</span> <span class="cp-phone"></span></div>
                                                <div class="col-8"><span class="text-muted">Лицензия:</span> <span class="cp-lic"></span></div>
                                                <div class="col-4"><span class="text-muted">до</span> <span class="cp-lic-date"></span></div>
                                            </div>
                                        </div>
                                        <div class="inn-error text-danger small mt-1" style="display:none;"></div>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-3 d-flex justify-content-center align-items-center" style="padding-top:28px;">
                                    <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="swapCompanies()" title="Поменять местами">
                                        <i class="bi bi-arrow-left-right d-none d-md-inline-block"></i>
                                        <i class="bi bi-arrow-down-up d-inline-block d-md-none"></i>
                                    </button>
                                </div>
                                @endif


                                <div class="col-md-{{ $hideProviderContract ? '12' : '5' }} mb-3">
                                    <label class="form-label fw-medium">Получатель <span class="text-danger">*</span></label>
                                    <div class="counterparty-widget" id="receiver-widget">
                                        <div class="input-group">
                                            <input type="text" class="form-control cp-search" id="receiver-search"
                                                placeholder="Введите название или ИНН..." autocomplete="off">
                                            <button class="btn btn-outline-secondary cp-add-btn" type="button"
                                                data-target="receiver" title="Добавить в справочник">
                                                <i class="bi bi-person-plus"></i>
                                            </button>
                                        </div>
                                        <div class="list-group position-absolute shadow cp-results" id="receiver-results"
                                            style="display:none; z-index:1050; width:100%; max-height:220px; overflow-y:auto;"></div>
                                        <input type="hidden" name="receiver" id="receiver-value" required>
                                        <input type="hidden" name="receiver_snapshot" id="receiver-snapshot">
                                        <div class="cp-details card mt-2 border-0 bg-light p-2" id="receiver-details" style="display:none; font-size:0.85rem;">
                                            <div class="row g-1">
                                                <div class="col-4"><span class="text-muted">ИНН:</span> <span class="fw-medium cp-inn"></span></div>
                                                <div class="col-4"><span class="text-muted">КПП:</span> <span class="fw-medium cp-kpp"></span></div>
                                                <div class="col-4"><span class="text-muted">ОГРН:</span> <span class="fw-medium cp-ogrn"></span></div>
                                                <div class="col-12"><span class="text-muted">Адрес:</span> <span class="cp-addr"></span></div>
                                                <div class="col-12"><span class="text-muted">Тел:</span> <span class="cp-phone"></span></div>
                                                <div class="col-8"><span class="text-muted">Лицензия:</span> <span class="cp-lic"></span></div>
                                                <div class="col-4"><span class="text-muted">до</span> <span class="cp-lic-date"></span></div>
                                            </div>
                                        </div>
                                        <div class="inn-error text-danger small mt-1" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">Информация об отходах</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addWasteItem()">
                                    <i class="bi bi-plus-circle"></i> Добавить отход
                                </button>
                            </div>
                            <div id="waste-items-container"></div>

                            <div id="waste-search-section" class="border rounded p-3 mb-3">
                                <div class="mb-3 position-relative">
                                    <label class="form-label">Поиск отхода</label>
                                    <input type="text" id="waste-search" class="form-control" autocomplete="off">
                                    <div id="waste-results" class="list-group position-absolute w-100 shadow-sm" style="display:none; z-index:1000; max-height:250px; overflow-y:auto;"></div>
                                </div>
                                <div id="selected-waste-display" class="alert alert-light border mb-3 d-none">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <div class="fw-bold" id="display-name"></div>
                                            <div class="small">Код: <span id="display-fkko"></span> | Класс: <span id="display-hazard"></span></div>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearWasteSelection()">Изменить</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Количество (тонн)</label>
                                        <input type="text" id="temp-amount" class="form-control" placeholder="0.000">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label d-block">Вид обращения</label>
                                        <div class="row">
                                            @if($presetOp)
                                                <div class="col-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="{{ $presetOp['id'] }}" value="{{ $presetOp['label'] }}"
                                                            checked onclick="return false;" style="pointer-events:none;">
                                                        <label class="form-check-label small fw-medium text-primary" for="{{ $presetOp['id'] }}">{{ $presetOp['label'] }}</label>
                                                    </div>
                                                </div>
                                            @elseif($actType === 'third_party')
                                                @foreach($thirdPartyOps as $opId => $opLabel)
                                                    <div class="col-md-6 col-12 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="{{ $opId }}" value="{{ $opLabel }}">
                                                            <label class="form-check-label small" for="{{ $opId }}">{{ $opLabel }}</label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                @foreach($operations as $opId => $opLabel)
                                                    <div class="col-md-6 col-12 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="{{ $opId }}" value="{{ $opLabel }}">
                                                            <label class="form-check-label small" for="{{ $opId }}">{{ $opLabel }}</label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary" onclick="addSelectedWaste()">Добавить в список</button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4 gap-2">
                                <a href="{{ route('acts.archive') }}" class="btn btn-outline-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-floppy"></i> Сохранить изменения
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="addCpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Добавить контрагента в справочник</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cp-modal-error" class="alert alert-danger d-none"></div>
                    <div class="mb-3">
                        <label class="form-label">Наименование <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cp-modal-name">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ИНН</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="cp-modal-inn" maxlength="12">
                                <button class="btn btn-outline-primary" type="button" id="cp-modal-inn-search" title="Найти по ИНН">
                                    <i class="bi bi-search"></i> Найти
                                </button>
                            </div>
                            <div class="form-text text-danger" id="cp-modal-inn-err" style="display:none;">Неверный ИНН</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">КПП</label>
                            <input type="text" class="form-control" id="cp-modal-kpp" maxlength="9">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ОГРН</label>
                        <input type="text" class="form-control" id="cp-modal-ogrn" maxlength="15" placeholder="13 или 15 цифр">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Юр. адрес</label>
                        <input type="text" class="form-control" id="cp-modal-addr">
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-7 mb-3">
                            <label class="form-label">Лицензия</label>
                            <input type="text" class="form-control" id="cp-modal-lic">
                        </div>
                        <div class="col-md-5 mb-3 pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cp-modal-lic-perpetual">
                                <label class="form-check-label" for="cp-modal-lic-perpetual">Бессрочная лицензия</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" class="form-control" id="cp-modal-phone">
                        </div>
                        <div class="col-md-6 mb-3" id="cp-modal-lic-date-wrap">
                            <label class="form-label">Срок действия лицензии</label>
                            <input type="date" class="form-control" id="cp-modal-lic-date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-dark" id="cp-modal-save">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>

    const prefillItems = @json($actData['items'] ?? []);
    const prefillProviderSnap = @json($pSnap ?? []);
    const prefillReceiverSnap = @json($rSnap ?? []);
    const prefillProviderName = @json($actData['provider'] ?? '');
    const prefillReceiverName = @json($actData['receiver'] ?? '');

    let wasteItems = [];
    let currentWasteData = null;
    let wasteItemCounter = 0;

    function validateInn(inn) {
        inn = inn.toString().replace(/\D/g, '');
        return inn.length > 9 && inn.length < 13;
    }

    function updateCpDetails(type, data) {
        const widget = document.getElementById(type + '-widget');
        if (!widget) return;
        const details = widget.querySelector('.cp-details');
        const searchInput = widget.querySelector('.cp-search');
        if (!data) {
            details.style.display = 'none';
            searchInput.value = '';
            document.getElementById(type + '-value').value = '';
            document.getElementById(type + '-snapshot').value = '';
            return;
        }
        searchInput.value = data.name || '';
        document.getElementById(type + '-value').value = data.name || '';
        details.querySelector('.cp-inn').textContent  = data.inn || '—';
        details.querySelector('.cp-kpp').textContent  = data.kpp || '—';
        details.querySelector('.cp-ogrn').textContent = data.ogrn || '—';
        details.querySelector('.cp-addr').textContent = data.legal_address || data.address || '—';
        details.querySelector('.cp-phone').textContent= data.phone || '—';
        details.querySelector('.cp-lic').textContent  = data.license_number || data.license || '—';
        const ld = data.license_valid_until || data.license_date || '';
        details.querySelector('.cp-lic-date').textContent = ld || '—';
        details.style.display = 'block';
        document.getElementById(type + '-snapshot').value = JSON.stringify(data);
    }

    function swapCompanies() {
        const pVal  = document.getElementById('provider-value')?.value  || '';
        const rVal  = document.getElementById('receiver-value')?.value  || '';
        const pSnap = document.getElementById('provider-snapshot')?.value || '';
        const rSnap = document.getElementById('receiver-snapshot')?.value || '';
        const pSearch = document.getElementById('provider-search')?.value || '';
        const rSearch = document.getElementById('receiver-search')?.value || '';

        const pData = pSnap ? (() => { try { return JSON.parse(pSnap); } catch{ return {name: pVal}; } })() : (pVal ? {name: pVal} : null);
        const rData = rSnap ? (() => { try { return JSON.parse(rSnap); } catch{ return {name: rVal}; } })() : (rVal ? {name: rVal} : null);

        updateCpDetails('provider', rData);
        updateCpDetails('receiver', pData);
    }

    function renderWasteList() {
        const container = document.getElementById('waste-items-container');
        container.innerHTML = '';
        wasteItems.forEach((item, i) => {
            const div = document.createElement('div');
            div.className = 'waste-item-card';
            div.innerHTML = `
                <button type="button" class="btn btn-sm btn-outline-danger remove-waste" onclick="removeWaste(${i})">
                    <i class="bi bi-x"></i>
                </button>
                <div class="row g-2">
                    <div class="col-md-5"><strong>${item.name}</strong></div>
                    <div class="col-md-3 text-muted small">ФККО: ${item.fkko_code}</div>
                    <div class="col-md-2 text-muted small">Класс: ${item.hazard_class}</div>
                    <div class="col-md-2 fw-bold">${item.amount} т</div>
                    <div class="col-12 text-muted small">Вид: ${item.operation_types}</div>
                </div>
                <input type="hidden" name="wastes[${i}][name]"           value="${item.name}">
                <input type="hidden" name="wastes[${i}][fkko_code]"      value="${item.fkko_code}">
                <input type="hidden" name="wastes[${i}][hazard_class]"   value="${item.hazard_class}">
                <input type="hidden" name="wastes[${i}][amount]"         value="${item.amount}">
                <input type="hidden" name="wastes[${i}][operation_types]" value="${item.operation_types}">
            `;
            container.appendChild(div);
        });
    }

    function removeWaste(i) { wasteItems.splice(i, 1); renderWasteList(); }

    function addWasteItem() {
        document.getElementById('waste-search-section').style.display = 'block';
        document.getElementById('waste-search').focus();
    }

    function clearWasteSelection() {
        currentWasteData = null;
        document.getElementById('selected-waste-display').classList.add('d-none');
        document.getElementById('waste-search').value = '';
        document.getElementById('waste-results').style.display = 'none';
        document.getElementById('waste-search').focus();
    }

    function addSelectedWaste() {
        if (!currentWasteData) return alert('Выберите отход');
        const amount = document.getElementById('temp-amount').value.trim().replace(',', '.');
        if (!amount || isNaN(amount)) return alert('Укажите количество');
        const ops = [];
        document.querySelectorAll('[id^="temp-op"]:checked').forEach(cb => ops.push(cb.value));
        if (ops.length === 0) return alert('Выберите вид обращения');

        wasteItems.push({
            name:           currentWasteData.name,
            fkko_code:      currentWasteData.code,
            hazard_class:   currentWasteData.hazard_class,
            amount:         parseFloat(amount),
            operation_types: ops.join(', '),
        });
        renderWasteList();


        currentWasteData = null;
        document.getElementById('temp-amount').value = '';
        document.getElementById('waste-search').value = '';
        document.getElementById('waste-results').style.display = 'none';
        document.getElementById('selected-waste-display').classList.add('d-none');
        document.querySelectorAll('[id^="temp-op"]').forEach(cb => { if (!cb.hasAttribute('data-preset')) cb.checked = false; });
        document.getElementById('waste-search-section').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {

        prefillItems.forEach(item => {
            wasteItems.push({
                name:            item.name || '',
                fkko_code:       item.fkko_code || '',
                hazard_class:    item.hazard_class || '',
                amount:          parseFloat(item.quantity || 0),
                operation_types: item.operation_type || '',
            });
        });
        renderWasteList();


        if (prefillProviderSnap && Object.keys(prefillProviderSnap).length > 0) {
            updateCpDetails('provider', prefillProviderSnap);
        } else if (prefillProviderName) {
            updateCpDetails('provider', { name: prefillProviderName });
        }
        if (prefillReceiverSnap && Object.keys(prefillReceiverSnap).length > 0) {
            updateCpDetails('receiver', prefillReceiverSnap);
        } else if (prefillReceiverName) {
            updateCpDetails('receiver', { name: prefillReceiverName });
        }


        ['provider', 'receiver'].forEach(type => {
            const input = document.getElementById(type + '-search');
            const res   = document.getElementById(type + '-results');
            if (!input || !res) return;
            let t = null;
            input.addEventListener('input', () => {
                clearTimeout(t);
                const q = input.value.trim();
                if (q.length < 2) return res.style.display = 'none';
                t = setTimeout(() => {
                    fetch('/counterparties/search?q=' + encodeURIComponent(q) + '&type=' + type)
                        .then(r => r.json()).then(data => {
                            res.innerHTML = data.map(cp => `
                                <a href="#" class="list-group-item list-group-item-action py-1 small"
                                   onclick="event.preventDefault(); window.selCP('${type}', ${JSON.stringify(cp).replace(/"/g, '&quot;')})">
                                    ${cp.name} <span class="badge bg-light text-dark border float-end">${cp.inn || ''}</span>
                                </a>
                            `).join('');
                            res.style.display = data.length ? 'block' : 'none';
                        });
                }, 300);
            });
            input.addEventListener('blur', () => setTimeout(() => res.style.display = 'none', 200));
        });

        window.selCP = (type, cp) => {
            updateCpDetails(type, cp);
            document.getElementById(type + '-results').style.display = 'none';
        };


        const cpModal = new bootstrap.Modal(document.getElementById('addCpModal'));
        let targetCP = '';
        document.querySelectorAll('.cp-add-btn').forEach(b => b.onclick = () => {
            targetCP = b.dataset.target;
            document.getElementById('cp-modal-name').value = document.getElementById(targetCP + '-search').value;
            cpModal.show();
        });

        document.getElementById('cp-modal-save').onclick = () => {
            const name = document.getElementById('cp-modal-name').value.trim();
            const inn  = document.getElementById('cp-modal-inn').value.trim();
            if (!name) return alert('Имя обязательно');
            if (inn && !validateInn(inn)) return alert('Неверный ИНН');
            fetch('/counterparties', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    name, inn, kpp: document.getElementById('cp-modal-kpp').value,
                    ogrn: document.getElementById('cp-modal-ogrn').value,
                    legal_address: document.getElementById('cp-modal-addr').value,
                    phone: document.getElementById('cp-modal-phone').value,
                    license_number: document.getElementById('cp-modal-lic').value,
                    license_perpetual: document.getElementById('cp-modal-lic-perpetual').checked,
                    license_valid_until: document.getElementById('cp-modal-lic-perpetual').checked
                        ? null : document.getElementById('cp-modal-lic-date').value,
                })
            }).then(r => r.json()).then(res => {
                if (res.error) alert(res.error);
                else { updateCpDetails(targetCP, res); cpModal.hide(); }
            });
        };


        document.getElementById('cp-modal-inn-search').addEventListener('click', async function () {
            const inn = document.getElementById('cp-modal-inn').value.trim();
            if (!inn || inn.length < 10) { alert('Введите ИНН (10 или 12 цифр)'); return; }
            const btn = this; const origHtml = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const resp = await fetch(`{{ route('checko.inn') }}?inn=${inn}`);
                if (!resp.ok) throw new Error((await resp.json()).error || 'Ошибка');
                const data = await resp.json();
                if (data.name) {
                    document.getElementById('cp-modal-name').value = data.name;
                    document.getElementById('cp-modal-kpp').value  = data.kpp || '';
                    document.getElementById('cp-modal-ogrn').value = data.ogrn || '';
                    document.getElementById('cp-modal-addr').value = data.address || '';
                    document.getElementById('cp-modal-phone').value = data.phone || '';
                    if (data.license_details) document.getElementById('cp-modal-lic').value = data.license_details;
                    const licPerpetual = document.getElementById('cp-modal-lic-perpetual');
                    const licDateWrap  = document.getElementById('cp-modal-lic-date-wrap');
                    const licDateInput = document.getElementById('cp-modal-lic-date');
                    if (data.license_valid_until) {
                        licPerpetual.checked = false; licDateWrap.style.opacity = '1';
                        licDateInput.disabled = false; licDateInput.value = data.license_valid_until;
                    } else if (data.license_details) {
                        licPerpetual.checked = true; licDateWrap.style.opacity = '0.4';
                        licDateInput.disabled = true; licDateInput.value = '';
                    }
                } else { alert('Организация не найдена'); }
            } catch(e) { alert('Ошибка: ' + e.message); }
            finally { btn.disabled = false; btn.innerHTML = origHtml; }
        });


        const licPerpetualCb = document.getElementById('cp-modal-lic-perpetual');
        if (licPerpetualCb) {
            licPerpetualCb.addEventListener('change', function() {
                const wrap = document.getElementById('cp-modal-lic-date-wrap');
                const dateInput = document.getElementById('cp-modal-lic-date');
                if (this.checked) { wrap.style.opacity = '0.4'; dateInput.disabled = true; dateInput.value = ''; }
                else { wrap.style.opacity = '1'; dateInput.disabled = false; }
            });
        }


        const wSearch = document.getElementById('waste-search');
        const wRes    = document.getElementById('waste-results');
        let wt = null;
        wSearch.addEventListener('input', () => {
            clearTimeout(wt);
            const q = wSearch.value.trim();
            if (q.length < 2) return wRes.style.display = 'none';
            wt = setTimeout(() => {
                fetch('/fkko/search?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                    wRes.innerHTML = data.map(i =>
                        `<a href="#" class="list-group-item list-group-item-action py-1 small"
                             onclick="event.preventDefault(); window.selW(${JSON.stringify(i).replace(/"/g, '&quot;')})">
                            ${i.name} <span class="badge bg-primary float-end">${i.code}</span>
                         </a>`
                    ).join('');
                    wRes.style.display = data.length ? 'block' : 'none';
                });
            }, 300);
        });

        window.selW = (item) => {
            currentWasteData = item;
            document.getElementById('display-name').textContent   = item.name;
            document.getElementById('display-fkko').textContent   = item.code;
            document.getElementById('display-hazard').textContent = item.hazard_class;
            document.getElementById('selected-waste-display').classList.remove('d-none');
            wRes.style.display = 'none';
        };
    });
</script>
@endpush
