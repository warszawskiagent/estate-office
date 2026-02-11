(() => {
  const bindAddAgreementInfo = () => {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.estateoffice-crm__add-agreement');
      if (!button) {
        return;
      }

      event.preventDefault();
      window.location.href = `${window.location.pathname}?eo_step=1`;
    });
  };

  const bindClientFormDynamics = () => {
    const typeSelect = document.querySelector('#eo-client-type');
    if (!typeSelect) {
      return;
    }

    const personBlocks = Array.from(document.querySelectorAll('.eo-person-only'));
    const companyBlocks = Array.from(document.querySelectorAll('.eo-company-only'));
    const sameCorrespondence = document.querySelector('#eo-same-correspondence');
    const correspondenceFields = document.querySelector('#eo-correspondence-fields');

    const switchType = () => {
      const isCompany = typeSelect.value === 'company';
      personBlocks.forEach((el) => {
        el.style.display = isCompany ? 'none' : '';
      });
      companyBlocks.forEach((el) => {
        el.style.display = isCompany ? '' : 'none';
      });
    };

    const switchCorrespondence = () => {
      if (!sameCorrespondence || !correspondenceFields) {
        return;
      }

      correspondenceFields.style.display = sameCorrespondence.checked ? 'none' : '';
    };

    typeSelect.addEventListener('change', switchType);
    if (sameCorrespondence) {
      sameCorrespondence.addEventListener('change', switchCorrespondence);
    }

    switchType();
    switchCorrespondence();
  };

  const bindAgreementStep1Dynamics = () => {
    const openEnded = document.querySelector('#eo-agreement-open-ended');
    const endDate = document.querySelector('#eo-agreement-end-date');

    if (!openEnded || !endDate) {
      return;
    }

    const sync = () => {
      endDate.disabled = openEnded.checked;
      if (openEnded.checked) {
        endDate.value = '';
      }
    };

    openEnded.addEventListener('change', sync);
    sync();
  };

  const bindAgreementClientTypeDynamics = () => {
    const select = document.querySelector('#eo-agreement-client-type');
    if (!select) {
      return;
    }

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

  bindAddAgreementInfo();
  bindClientFormDynamics();
  bindAgreementStep1Dynamics();
  bindAgreementClientTypeDynamics();
})();
