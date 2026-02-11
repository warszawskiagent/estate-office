(() => {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('.estateoffice-crm__add-agreement');
    if (!button) {
      return;
    }

    event.preventDefault();
    window.alert('Formularz dodawania umowy będzie dostępny od wersji 0.4.');
  });
})();
