(() => {
  const lineList = document.querySelector('#order-lines');
  if (!lineList) return;

  const totalOutput = document.querySelector('#order-total');
  const formatRp = (cents) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0
  }).format(cents / 100);

  const updateTotal = () => {
    let total = 0;
    lineList.querySelectorAll('.order-line').forEach((line) => {
      const option = line.querySelector('.menu-select')?.selectedOptions[0];
      const price = Number(option?.dataset.price || 0);
      const quantity = Math.max(0, Number(line.querySelector('[name="quantities[]"]')?.value || 0));
      total += Math.round(price * 100) * quantity;
    });
    if (totalOutput) totalOutput.textContent = formatRp(total);
  };

  const addLine = () => {
    const first = lineList.querySelector('.order-line');
    if (!first) return;
    const clone = first.cloneNode(true);
    clone.querySelector('.menu-select').selectedIndex = 0;
    clone.querySelector('[name="quantities[]"]').value = '1';
    lineList.appendChild(clone);
    updateTotal();
  };

  document.querySelector('#add-order-line')?.addEventListener('click', addLine);
  lineList.addEventListener('input', updateTotal);
  lineList.addEventListener('change', updateTotal);
  lineList.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-line');
    if (!button) return;
    const lines = lineList.querySelectorAll('.order-line');
    if (lines.length > 1) button.closest('.order-line').remove();
    else {
      lines[0].querySelector('.menu-select').selectedIndex = 0;
      lines[0].querySelector('[name="quantities[]"]').value = '1';
    }
    updateTotal();
  });
  updateTotal();
})();
