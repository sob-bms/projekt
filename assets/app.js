document.addEventListener('DOMContentLoaded', function () {
    const tilfoejKnap = document.getElementById('tilfoej-ue');
    if (!tilfoejKnap) return;

    const vaelg = document.getElementById('ue-vaelg');
    const tabelKrop = document.querySelector('#ue-tabel tbody');

    function erAlleredeTilfoejet(id) {
        return !!tabelKrop.querySelector('input[name="ue_id[]"][value="' + id + '"]');
    }

    tilfoejKnap.addEventListener('click', function () {
        const id = vaelg.value;
        if (!id || erAlleredeTilfoejet(id)) return;
        const navn = vaelg.options[vaelg.selectedIndex].dataset.navn;

        const raekke = document.createElement('tr');

        const navnCelle = document.createElement('td');
        navnCelle.appendChild(document.createTextNode(navn));
        const skjultInput = document.createElement('input');
        skjultInput.type = 'hidden';
        skjultInput.name = 'ue_id[]';
        skjultInput.value = id;
        navnCelle.appendChild(skjultInput);

        const sumCelle = document.createElement('td');
        const sumInput = document.createElement('input');
        sumInput.type = 'number';
        sumInput.step = '0.01';
        sumInput.name = 'ue_sum[]';
        sumCelle.appendChild(sumInput);

        const handlingCelle = document.createElement('td');
        const fjernKnap = document.createElement('button');
        fjernKnap.type = 'button';
        fjernKnap.className = 'fjern-ue link-knap';
        fjernKnap.textContent = 'Fjern';
        handlingCelle.appendChild(fjernKnap);

        raekke.append(navnCelle, sumCelle, handlingCelle);
        tabelKrop.appendChild(raekke);
        vaelg.value = '';
    });

    tabelKrop.addEventListener('click', function (event) {
        if (event.target.classList.contains('fjern-ue')) {
            event.target.closest('tr').remove();
        }
    });
});
