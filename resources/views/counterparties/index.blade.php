@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">Справочник контрагентов</h4>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control" placeholder="Поиск по названию или ИНН..."
                value="{{ request('q') }}" style="max-width: 400px;">
            <button type="submit" class="btn btn-primary">Найти</button>
            @if(request('q'))
                <a href="{{ route('counterparties.index') }}" class="btn btn-outline-secondary">Сбросить</a>
            @endif
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Наименование</th>
                        <th>ИНН</th>
                        <th>КПП</th>
                        <th>ОГРН</th>
                        <th>Юр. адрес</th>
                        <th>Лицензия</th>
                        <th>Срок лицензии</th>
                        <th width="100"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counterparties as $cp)
                        <tr id="cp-row-{{ $cp->id }}">
                            <td class="fw-medium">{{ $cp->name }}</td>
                            <td>{{ $cp->inn ?? '—' }}</td>
                            <td>{{ $cp->kpp ?? '—' }}</td>
                            <td>{{ $cp->ogrn ?? '—' }}</td>
                            <td>{{ $cp->legal_address ?? '—' }}</td>
                            <td>{{ $cp->license_number ?? '—' }}</td>
                            <td>{{ $cp->license_perpetual ? 'бессрочная' : ($cp->license_valid_until?->format('d.m.Y') ?? '—') }}</td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary edit-cp me-1"
                                    title="Редактировать"
                                    data-id="{{ $cp->id }}"
                                    data-name="{{ $cp->name }}"
                                    data-inn="{{ $cp->inn }}"
                                    data-kpp="{{ $cp->kpp }}"
                                    data-ogrn="{{ $cp->ogrn }}"
                                    data-addr="{{ $cp->legal_address }}"
                                    data-phone="{{ $cp->phone }}"
                                    data-lic="{{ $cp->license_number }}"
                                    data-lic-perpetual="{{ $cp->license_perpetual ? '1' : '0' }}"
                                    data-lic-date="{{ $cp->license_valid_until ? $cp->license_valid_until->format('Y-m-d') : '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-cp" data-id="{{ $cp->id }}" title="Удалить">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <div class="mb-2"><i class="bi bi-people display-4 opacity-25"></i></div>
                                Справочник пуст. Контрагенты добавляются при создании актов.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($counterparties->hasPages())
    <div class="mt-4">{{ $counterparties->links() }}</div>
@endif

{{-- Модальное окно редактирования контрагента --}}
<div class="modal fade" id="editCpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Редактировать контрагента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="edit-modal-error" class="alert alert-danger d-none"></div>
                <div id="edit-modal-success" class="alert alert-success d-none">Данные сохранены.</div>

                <div class="mb-3">
                    <label class="form-label">Наименование <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit-cp-name">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ИНН</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="edit-cp-inn" maxlength="12" placeholder="10 или 12 цифр">
                            <button class="btn btn-outline-primary" type="button" id="edit-cp-inn-search" title="Найти по ИНН">
                                <i class="bi bi-search"></i> Найти
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">КПП</label>
                        <input type="text" class="form-control" id="edit-cp-kpp" maxlength="9">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ОГРН</label>
                    <input type="text" class="form-control" id="edit-cp-ogrn" maxlength="15" placeholder="13 или 15 цифр">
                </div>
                <div class="mb-3">
                    <label class="form-label">Юр. адрес</label>
                    <input type="text" class="form-control" id="edit-cp-addr">
                </div>
                <div class="mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="text" class="form-control" id="edit-cp-phone">
                </div>
                <div class="row align-items-end">
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Лицензия</label>
                        <input type="text" class="form-control" id="edit-cp-lic">
                    </div>
                    <div class="col-md-5 mb-3 pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit-cp-lic-perpetual">
                            <label class="form-check-label" for="edit-cp-lic-perpetual">Бессрочная лицензия</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3" id="edit-cp-lic-date-wrap">
                        <label class="form-label">Срок действия лицензии</label>
                        <input type="date" class="form-control" id="edit-cp-lic-date">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary px-4" id="edit-cp-save">
                    <i class="bi bi-check-circle me-1"></i>Сохранить
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;


document.querySelectorAll('.delete-cp').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('Удалить контрагента из справочника?')) return;
        const id = this.dataset.id;
        fetch('/counterparties/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).then(r => r.json()).then(data => {
            if (data.success) document.getElementById('cp-row-' + id)?.remove();
        });
    });
});


let editCpId = null;
const editModal = new bootstrap.Modal(document.getElementById('editCpModal'));

document.querySelectorAll('.edit-cp').forEach(btn => {
    btn.addEventListener('click', function () {
        editCpId = this.dataset.id;

        document.getElementById('edit-cp-name').value    = this.dataset.name    || '';
        document.getElementById('edit-cp-inn').value     = this.dataset.inn     || '';
        document.getElementById('edit-cp-kpp').value     = this.dataset.kpp     || '';
        document.getElementById('edit-cp-ogrn').value    = this.dataset.ogrn    || '';
        document.getElementById('edit-cp-addr').value    = this.dataset.addr    || '';
        document.getElementById('edit-cp-phone').value   = this.dataset.phone   || '';
        document.getElementById('edit-cp-lic').value     = this.dataset.lic     || '';
        document.getElementById('edit-cp-lic-date').value = this.dataset.licDate || '';

        const isPerpetual = this.dataset.licPerpetual === '1';
        const perpetualCb = document.getElementById('edit-cp-lic-perpetual');
        const licDateWrap = document.getElementById('edit-cp-lic-date-wrap');
        const licDateInput = document.getElementById('edit-cp-lic-date');
        perpetualCb.checked = isPerpetual;
        licDateWrap.style.opacity = isPerpetual ? '0.4' : '1';
        licDateInput.disabled = isPerpetual;

        document.getElementById('edit-modal-error').classList.add('d-none');
        document.getElementById('edit-modal-success').classList.add('d-none');

        editModal.show();
    });
});


document.getElementById('edit-cp-lic-perpetual').addEventListener('change', function () {
    const wrap = document.getElementById('edit-cp-lic-date-wrap');
    const dateInput = document.getElementById('edit-cp-lic-date');
    if (this.checked) {
        wrap.style.opacity = '0.4';
        dateInput.disabled = true;
        dateInput.value = '';
    } else {
        wrap.style.opacity = '1';
        dateInput.disabled = false;
    }
});


document.getElementById('edit-cp-inn-search').addEventListener('click', async function () {
    const inn = document.getElementById('edit-cp-inn').value.trim();
    if (!inn || inn.length < 10) {
        alert('Пожалуйста, введите ИНН (10 или 12 цифр)');
        return;
    }
    const btn = this;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    try {
        const response = await fetch(`{{ route('checko.inn') }}?inn=${inn}`);
        if (!response.ok) {
            const err = await response.json();
            throw new Error(err.error || 'Ошибка при поиске');
        }
        const data = await response.json();
        if (data.name) {
            document.getElementById('edit-cp-name').value  = data.name    || '';
            document.getElementById('edit-cp-kpp').value   = data.kpp     || '';
            document.getElementById('edit-cp-ogrn').value  = data.ogrn    || '';
            document.getElementById('edit-cp-addr').value  = data.address || '';
            document.getElementById('edit-cp-phone').value = data.phone   || '';
            if (data.license_details) {
                document.getElementById('edit-cp-lic').value = data.license_details;
            }
            const perpetualCb  = document.getElementById('edit-cp-lic-perpetual');
            const licDateWrap  = document.getElementById('edit-cp-lic-date-wrap');
            const licDateInput = document.getElementById('edit-cp-lic-date');
            if (data.license_valid_until) {
                perpetualCb.checked = false;
                licDateWrap.style.opacity = '1';
                licDateInput.disabled = false;
                licDateInput.value = data.license_valid_until;
            } else if (data.license_details) {
                perpetualCb.checked = true;
                licDateWrap.style.opacity = '0.4';
                licDateInput.disabled = true;
                licDateInput.value = '';
            }
        } else {
            alert('Организация не найдена по указанному ИНН');
        }
    } catch (e) {
        alert('Ошибка: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
});


document.getElementById('edit-cp-save').addEventListener('click', async function () {
    const name = document.getElementById('edit-cp-name').value.trim();
    if (!name) { alert('Наименование обязательно'); return; }

    const isPerpetual = document.getElementById('edit-cp-lic-perpetual').checked;

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

    try {
        const response = await fetch('/counterparties/' + editCpId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                name,
                inn:                 document.getElementById('edit-cp-inn').value.trim()  || null,
                kpp:                 document.getElementById('edit-cp-kpp').value.trim()  || null,
                ogrn:                document.getElementById('edit-cp-ogrn').value.trim() || null,
                legal_address:       document.getElementById('edit-cp-addr').value.trim() || null,
                phone:               document.getElementById('edit-cp-phone').value.trim()|| null,
                license_number:      document.getElementById('edit-cp-lic').value.trim()  || null,
                license_perpetual:   isPerpetual,
                license_valid_until: isPerpetual ? null : (document.getElementById('edit-cp-lic-date').value || null),
            })
        });

        const data = await response.json();

        if (!response.ok || data.error) {
            document.getElementById('edit-modal-error').textContent = data.error || 'Ошибка сохранения';
            document.getElementById('edit-modal-error').classList.remove('d-none');
        } else {

            const row = document.getElementById('cp-row-' + editCpId);
            if (row) {
                const cells = row.querySelectorAll('td');
                const cp = data.counterparty;
                cells[0].textContent = cp.name;
                cells[1].textContent = cp.inn  || '—';
                cells[2].textContent = cp.kpp  || '—';
                cells[3].textContent = cp.ogrn || '—';
                cells[4].textContent = cp.legal_address  || '—';
                cells[5].textContent = cp.license_number || '—';
                cells[6].textContent = cp.license_perpetual
                    ? 'бессрочная'
                    : (cp.license_valid_until
                        ? new Date(cp.license_valid_until).toLocaleDateString('ru-RU')
                        : '—');

                const editBtn = row.querySelector('.edit-cp');
                editBtn.dataset.name         = cp.name;
                editBtn.dataset.inn          = cp.inn          || '';
                editBtn.dataset.kpp          = cp.kpp          || '';
                editBtn.dataset.ogrn         = cp.ogrn         || '';
                editBtn.dataset.addr         = cp.legal_address || '';
                editBtn.dataset.phone        = cp.phone        || '';
                editBtn.dataset.lic          = cp.license_number || '';
                editBtn.dataset.licPerpetual = cp.license_perpetual ? '1' : '0';
                editBtn.dataset.licDate      = cp.license_valid_until || '';
            }
            document.getElementById('edit-modal-success').classList.remove('d-none');
            document.getElementById('edit-modal-error').classList.add('d-none');
            setTimeout(() => editModal.hide(), 800);
        }
    } catch (e) {
        document.getElementById('edit-modal-error').textContent = 'Ошибка: ' + e.message;
        document.getElementById('edit-modal-error').classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Сохранить';
    }
});
</script>
@endpush
