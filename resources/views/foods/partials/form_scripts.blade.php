<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ===============================
           AVAILABILITY SCHEDULING - FIXED FOR EDIT BLADE
        =============================== */

        const toggleBtn = document.getElementById('toggle-availability');
        const availabilitySection = document.getElementById('availability-section');
        const availabilitySummary = document.getElementById('availability-summary');
        const dayCheckboxes = document.querySelectorAll('.day-checkbox');
        const selectAllDays = document.getElementById('select_all_days');

        if (selectAllDays) {
            selectAllDays.addEventListener('change', function () {

                const checked = this.checked;

                dayCheckboxes.forEach(cb => {
                    cb.checked = checked;
                    cb.dispatchEvent(new Event('change'));
                });

            });
        }

        if (toggleBtn && availabilitySection) {
            toggleBtn.addEventListener('click', function() {
                const isVisible = availabilitySection.style.display !== 'none';
                availabilitySection.style.display = isVisible ? 'none' : 'block';
                if (availabilitySummary) {
                    availabilitySummary.style.display = isVisible ? 'block' : 'none';
                }
            });
        }


        if (dayCheckboxes.length > 0) {
            dayCheckboxes.forEach(cb => {

                cb.addEventListener('change', function () {

                    const day = this.dataset.day;
                    const group = document.querySelector(`.day-timings-group[data-day="${day}"]`);
                    if (!group) return;

                    group.style.display = this.checked ? 'block' : 'none';

                    if (this.checked) {
                        const timingsList = group.querySelector('.timings-list');
                        if (timingsList && timingsList.children.length === 0) {
                            addTimeSlot(day);
                        }
                    }

                    // ✅ Update Select All checkbox
                    if (selectAllDays) {
                        const total = dayCheckboxes.length;
                        const checked = document.querySelectorAll('.day-checkbox:checked').length;
                        selectAllDays.checked = total === checked;
                    }

                });

            });
        }
        window.addTimeSlot = function(day) {
            const timingsList = document.querySelector(`.timings-list[data-day="${day}"]`);
            if (!timingsList) return;

            let maxIndex = -1;
            timingsList.querySelectorAll('.timing-row').forEach(row => {
                const index = parseInt(row.dataset.index || -1);
                if (index > maxIndex) maxIndex = index;
            });
            const newIndex = maxIndex + 1;

            const slotHtml = `
                <div class="row align-items-end mb-2 timing-row" data-day="${day}" data-index="${newIndex}">
                    <div class="col-md-4">
                        <label class="form-label small">From</label>
                        <input type="time"
                               name="available_timings[${day}][${newIndex}][from]"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">To</label>
                        <input type="time"
                               name="available_timings[${day}][${newIndex}][to]"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-time-slot w-100">
                            <i class="fa fa-times mr-1"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            timingsList.insertAdjacentHTML('beforeend', slotHtml);
        };

        document.addEventListener('click', function(e) {
            if (e.target.closest('.add-time-slot')) {
                e.preventDefault();
                const day = e.target.closest('.add-time-slot').dataset.day;
                addTimeSlot(day);
                return;
            }

            if (e.target.closest('.remove-time-slot')) {
                e.preventDefault();
                const row = e.target.closest('.timing-row');
                if (row) row.remove();
                return;
            }

            if (e.target.closest('[data-addons-add]')) {
                e.preventDefault();
                const template = document.getElementById('add-on-template');
                const container = document.querySelector('[data-addons-container]');
                if (template && container) {
                    container.appendChild(template.content.cloneNode(true));
                }
                return;
            }

            if (e.target.closest('[data-specs-add]')) {
                e.preventDefault();
                const template = document.getElementById('spec-template');
                const container = document.querySelector('[data-specs-container]');
                if (template && container) {
                    container.appendChild(template.content.cloneNode(true));
                }
                return;
            }

            if (e.target.closest('[data-remove-row]')) {
                e.preventDefault();
                const row = e.target.closest('.repeatable-row');
                if (row) row.remove();
            }
        });
        // ✅ Auto check "Select All Days" if all days already selected
        if (selectAllDays) {
            const total = dayCheckboxes.length;
            const checked = document.querySelectorAll('.day-checkbox:checked').length;
            selectAllDays.checked = total === checked;
        }

        document.querySelectorAll('.day-checkbox:checked').forEach(cb => {
            const day = cb.dataset.day;
            const group = document.querySelector(`.day-timings-group[data-day="${day}"]`);
            if (group) group.style.display = 'block';
        });

        document.querySelectorAll('.day-checkbox:checked').forEach(cb => {
            const day = cb.dataset.day;
            const group = document.querySelector(`.day-timings-group[data-day="${day}"]`);
            if (group) group.style.display = 'block';
        });
    });

    /* ===============================
       PRODUCT OPTIONS (EDIT)
    =============================== */

    const masterOptions = @json($masterOptions ?? []);
    let selectedOptions = @json($food->options ?? []);

    window.openEditOptionsModal = function () {
        let html = `
<div class="modal fade" id="editOptionsModal">
 <div class="modal-dialog modal-md">
  <div class="modal-content">
   <div class="modal-header">
    <h5 class="modal-title">Product Options</h5>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
   </div>
   <div class="modal-body">
`;

        if (!masterOptions.length) {
            html += `<p class="text-muted text-center">No options available</p>`;
        } else {
            masterOptions.forEach(opt => {
                const selected = selectedOptions.find(sel =>
                    sel.id === opt.id &&
                    sel.title === opt.title &&
                    sel.subtitle === opt.subtitle
                );

                const checked = !!selected;
                const optionId = 'opt_' + Math.random().toString(36).substr(2, 9);

                html += `
<div class="form-check mb-2 d-flex align-items-center gap-2">
    <input type="checkbox"
           id="${optionId}"
           class="form-check-input option-checkbox"
           ${checked ? 'checked' : ''}
           data-id="${opt.id}"
           data-title="${opt.title ?? ''}"
           data-subtitle="${opt.subtitle ?? ''}"
           data-default-price="${opt.price ?? 0}">

    <label class="form-check-label mr-2" for="${optionId}">
        ${opt.title}
        ${opt.subtitle ? ` – ${opt.subtitle}` : ''}
    </label>

   <input type="number"
       class="form-control form-control-sm option-original-price-input"
       style="width:90px"
       placeholder="Original"
       value="${checked ? (selected.original_price ?? opt.original_price ?? opt.price) : (opt.original_price ?? opt.price)}">

<input type="number"
       class="form-control form-control-sm option-price-input ml-1"
       style="width:90px"
       placeholder="Price"
       value="${checked ? selected.price : opt.price}">
</div>
`;
            });
        }

        html += `
   </div>
   <div class="modal-footer">
    <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" onclick="saveEditOptions()">Save</button>
   </div>
  </div>
 </div>
</div>
`;

        $('#editOptionsModal').remove();
        document.body.insertAdjacentHTML('beforeend', html);
        $('#editOptionsModal').modal('show');

        $('#editOptionsModal').on('hidden.bs.modal', function () {
            $(this).remove();
        });
    };

    window.saveEditOptions = function () {
        selectedOptions = [];

        document.querySelectorAll('.option-checkbox').forEach(cb => {

            const wrapper = cb.closest('.form-check');

            const originalInput = wrapper?.querySelector('.option-original-price-input');
            const priceInput    = wrapper?.querySelector('.option-price-input');

            const originalPrice =
                originalInput && originalInput.value !== ''
                    ? originalInput.value
                    : cb.dataset.defaultPrice;

            const finalPrice =
                priceInput && priceInput.value !== ''
                    ? priceInput.value
                    : cb.dataset.defaultPrice;

            if (cb.checked) {

                selectedOptions.push({
                    id: cb.dataset.id,
                    title: cb.dataset.title || '',
                    subtitle: cb.dataset.subtitle || '',
                    original_price: originalPrice || 0,
                    price: finalPrice || 0,
                    is_available: true
                });
            }
        });

        document.getElementById('options_json').value =
            JSON.stringify(selectedOptions);

        const preview = document.getElementById('options-preview');

        if (selectedOptions.length) {
            preview.innerHTML =
                '<ul>' +
                selectedOptions.map(o =>
                    `<li>
            <strong>${o.title}</strong>
            ${o.subtitle ? ' – ' + o.subtitle : ''}
            (Original: ₹${o.original_price} | Price: ₹${o.price})
        </li>`
                ).join('') +
                '</ul>';
        } else {
            preview.innerHTML =
                '<span class="text-muted">No options selected</span>';
        }

        $('#editOptionsModal').modal('hide');
    };
</script>
