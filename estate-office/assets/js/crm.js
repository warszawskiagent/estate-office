(() => {
  const bindAddAgreementInfo = () => {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.estateoffice-crm__add-agreement');
      if (!button) {
        return;
      }

      event.preventDefault();
      window.alert('Formularz dodawania umowy będzie dostępny od wersji 0.4.');
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

  bindAddAgreementInfo();
  bindClientFormDynamics();
})();
