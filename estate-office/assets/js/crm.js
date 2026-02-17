(() => {
  const bindAddAgreementInfo = () => {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.estateoffice-crm__add-agreement');
      if (!button) {
        return;
      }

      event.preventDefault();
      window.location.href = `${window.location.pathname}?eo_view_mode=wizard&eo_step=1`;
    });
  };

  const bindClientFormDynamics = () => {
    const typeSelect = document.querySelector('#eo-client-type');
    if (!typeSelect) return;

    const personBlocks = Array.from(document.querySelectorAll('.eo-person-only'));
    const companyBlocks = Array.from(document.querySelectorAll('.eo-company-only'));
    const sameCorrespondence = document.querySelector('#eo-same-correspondence');
    const correspondenceFields = document.querySelector('#eo-correspondence-fields');

    const switchType = () => {
      const isCompany = typeSelect.value === 'company';
      personBlocks.forEach((el) => { el.style.display = isCompany ? 'none' : ''; });
      companyBlocks.forEach((el) => { el.style.display = isCompany ? '' : 'none'; });
    };

    const switchCorrespondence = () => {
      if (!sameCorrespondence || !correspondenceFields) return;
      correspondenceFields.style.display = sameCorrespondence.checked ? 'none' : '';
    };

    typeSelect.addEventListener('change', switchType);
    sameCorrespondence?.addEventListener('change', switchCorrespondence);
    switchType();
    switchCorrespondence();
  };

  const bindAgreementStep1Dynamics = () => {
    const openEnded = document.querySelector('#eo-agreement-open-ended');
    const endDate = document.querySelector('#eo-agreement-end-date');
    if (!openEnded || !endDate) return;

    const sync = () => {
      endDate.disabled = openEnded.checked;
      if (openEnded.checked) endDate.value = '';
    };

    openEnded.addEventListener('change', sync);
    sync();
  };

  const bindAgreementClientTypeDynamics = () => {
    const select = document.querySelector('#eo-agreement-client-type');
    if (!select) return;

    const person = document.querySelector('.eo-agreement-person-fields');
    const company = document.querySelector('.eo-agreement-company-fields');

    const sync = () => {
      const isCompany = select.value === 'company';
      if (person) person.style.display = isCompany ? 'none' : '';
      if (company) company.style.display = isCompany ? '' : 'none';
    };

    select.addEventListener('change', sync);
    sync();
  };

  const bindPropertyFormDynamics = () => {
    const typeSelect = document.querySelector('#eo-property-type');
    if (!typeSelect) return;

    const onlyPlot = Array.from(document.querySelectorAll('.eo-only-plot'));
    const onlyHouse = Array.from(document.querySelectorAll('.eo-only-house'));
    const onlyHousePlot = Array.from(document.querySelectorAll('.eo-only-house-plot'));
    const notPlot = Array.from(document.querySelectorAll('.eo-not-plot-only'));

    const priceInput = document.querySelector('#eo-price');
    const areaInput = document.querySelector('#eo-area');
    const ppm2Input = document.querySelector('#eo-price-per-m2');

    const noKw = document.querySelector('#eo-no-land-register');
    const kwInput = document.querySelector('#eo-land-register');

    const syncType = () => {
      const val = typeSelect.value;
      const isPlot = val === 'DZIAŁKA';
      const isHouse = val === 'DOM';

      onlyPlot.forEach((el) => { el.style.display = isPlot ? '' : 'none'; });
      onlyHouse.forEach((el) => { el.style.display = isHouse ? '' : 'none'; });
      onlyHousePlot.forEach((el) => { el.style.display = isPlot || isHouse ? '' : 'none'; });
      notPlot.forEach((el) => { el.style.display = isPlot ? 'none' : ''; });
    };

    const syncPpm2 = () => {
      if (!priceInput || !areaInput || !ppm2Input) return;
      const price = Number(priceInput.value || 0);
      const area = Number(areaInput.value || 0);
      ppm2Input.value = area > 0 ? (price / area).toFixed(2) : '0.00';
    };

    const syncKw = () => {
      if (!noKw || !kwInput) return;
      kwInput.disabled = noKw.checked;
      if (noKw.checked) kwInput.value = '';
    };

    typeSelect.addEventListener('change', syncType);
    priceInput?.addEventListener('input', syncPpm2);
    areaInput?.addEventListener('input', syncPpm2);
    noKw?.addEventListener('change', syncKw);

    syncType();
    syncPpm2();
    syncKw();
  };

  bindAddAgreementInfo();
  bindClientFormDynamics();
  bindAgreementStep1Dynamics();
  bindAgreementClientTypeDynamics();
  bindPropertyFormDynamics();
})();
