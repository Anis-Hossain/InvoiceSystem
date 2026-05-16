/* =====================================================
   Invoice Management System — app.js
   ===================================================== */

// ---- Invoice line items ----
let rowCount = 0;

function addItemRow(desc='', qty=1, price=0) {
    rowCount++;
    const tbody = document.getElementById('items-body');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.dataset.row = rowCount;
    tr.innerHTML = `
        <td><input type="text" name="items[${rowCount}][description]" placeholder="Item description" value="${desc}" required></td>
        <td><input type="number" name="items[${rowCount}][quantity]" class="qty" placeholder="1" value="${qty}" min="0.01" step="0.01" required oninput="recalcRow(this)"></td>
        <td><input type="number" name="items[${rowCount}][unit_price]" class="price" placeholder="0.00" value="${price}" min="0" step="0.01" required oninput="recalcRow(this)"></td>
        <td><input type="number" name="items[${rowCount}][total]" class="row-total" placeholder="0.00" value="${(qty*price).toFixed(2)}" readonly></td>
        <td><button type="button" class="remove-row" onclick="removeRow(this)" title="Remove">✕</button></td>
    `;
    tbody.appendChild(tr);
    updateTotals();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateTotals();
}

function recalcRow(input) {
    const tr = input.closest('tr');
    const qty   = parseFloat(tr.querySelector('.qty').value)   || 0;
    const price = parseFloat(tr.querySelector('.price').value) || 0;
    tr.querySelector('.row-total').value = (qty * price).toFixed(2);
    updateTotals();
}

function updateTotals() {
    const totals = document.querySelectorAll('.row-total');
    let sub = 0;
    totals.forEach(t => sub += parseFloat(t.value) || 0);

    const taxRate  = parseFloat(document.getElementById('tax_rate')?.value)  || 0;
    const discount = parseFloat(document.getElementById('discount')?.value)   || 0;
    const taxAmt   = sub * (taxRate / 100);
    const total    = sub + taxAmt - discount;

    setVal('subtotal_display', sub.toFixed(2));
    setVal('tax_amount_display', taxAmt.toFixed(2));
    setVal('total_display', total.toFixed(2));
    setVal('subtotal_hidden', sub.toFixed(2));
    setVal('tax_amount_hidden', taxAmt.toFixed(2));
    setVal('total_hidden', total.toFixed(2));
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.tagName === 'INPUT' ? el.value = val : el.textContent = val;
}

// ---- Confirm dialogs ----
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this record?');
}

// ---- Auto-dismiss alerts ----
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s'; setTimeout(()=>el.remove(), 500); }, 4000);
    });

    // Init totals if edit form loaded with items
    if (document.getElementById('items-body')) updateTotals();
});

// ---- Print Invoice ----
function printInvoice() {
    window.print();
}

// ---- Search filter (client-side table filtering) ----
function filterTable(inputId, tableId) {
    const val = document.getElementById(inputId).value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
