document.addEventListener('DOMContentLoaded', function () {
    lavVirksomhedTabel();
    lavKontaktTabel();
    lavTabtAarsagToggle();
});

function lavVirksomhedTabel() {
    const tilfoejKnap = document.getElementById('tilfoej-virksomhed');
    if (!tilfoejKnap) return;

    const vaelg = document.getElementById('virksomhed-vaelg');
    const tabelKrop = document.querySelector('#virksomhed-tabel tbody');

    function erAlleredeTilfoejet(id) {
        return !!tabelKrop.querySelector('input[name="virksomhed_id[]"][value="' + id + '"]');
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
        skjultInput.name = 'virksomhed_id[]';
        skjultInput.value = id;
        navnCelle.appendChild(skjultInput);

        const rolleCelle = document.createElement('td');
        const rolleVaelg = document.createElement('select');
        rolleVaelg.name = 'virksomhed_rolle[]';
        ['Kunde', 'Hovedentreprenør', 'Underentreprenør', 'Rådgiver', 'Leverandør', 'Andet'].forEach(function (rolle) {
            const option = document.createElement('option');
            option.value = rolle;
            option.textContent = rolle;
            rolleVaelg.appendChild(option);
        });
        rolleCelle.appendChild(rolleVaelg);

        const fagCelle = document.createElement('td');
        const fagInput = document.createElement('input');
        fagInput.type = 'text';
        fagInput.name = 'virksomhed_fag[]';
        fagCelle.appendChild(fagInput);

        const sumCelle = document.createElement('td');
        const sumInput = document.createElement('input');
        sumInput.type = 'number';
        sumInput.step = '0.01';
        sumInput.name = 'virksomhed_sum[]';
        sumCelle.appendChild(sumInput);

        const handlingCelle = document.createElement('td');
        const fjernKnap = document.createElement('button');
        fjernKnap.type = 'button';
        fjernKnap.className = 'fjern-raekke link-knap';
        fjernKnap.textContent = 'Fjern';
        handlingCelle.appendChild(fjernKnap);

        raekke.append(navnCelle, rolleCelle, fagCelle, sumCelle, handlingCelle);
        tabelKrop.appendChild(raekke);
        vaelg.value = '';
    });

    tabelKrop.addEventListener('click', function (event) {
        if (event.target.classList.contains('fjern-raekke')) {
            event.target.closest('tr').remove();
        }
    });
}

function lavKontaktTabel() {
    const tilfoejKnap = document.getElementById('tilfoej-kontakt');
    if (!tilfoejKnap) return;

    const tabelKrop = document.querySelector('#kontakt-tabel tbody');

    function nytFelt(type, navn) {
        const input = document.createElement('input');
        input.type = type;
        input.name = navn;
        return input;
    }

    // primaer_kontakt_index skal matche rækkens position blandt de
    // indsendte kontakt_navn[]-felter, som browseren nummererer efter
    // DOM-rækkefølge - så indekserne skal genberegnes, hver gang en række
    // tilføjes eller fjernes.
    function genberegnPrimaerIndekser() {
        tabelKrop.querySelectorAll('tr').forEach(function (raekke, indeks) {
            const radio = raekke.querySelector('input[name="primaer_kontakt_index"]');
            if (radio) radio.value = indeks;
        });
    }

    tilfoejKnap.addEventListener('click', function () {
        const raekke = document.createElement('tr');

        const navnCelle = document.createElement('td');
        navnCelle.appendChild(nytFelt('text', 'kontakt_navn[]'));
        const stillingCelle = document.createElement('td');
        stillingCelle.appendChild(nytFelt('text', 'kontakt_stilling[]'));
        const telefonCelle = document.createElement('td');
        telefonCelle.appendChild(nytFelt('text', 'kontakt_telefon[]'));
        const emailCelle = document.createElement('td');
        emailCelle.appendChild(nytFelt('email', 'kontakt_email[]'));

        const primaerCelle = document.createElement('td');
        primaerCelle.appendChild(nytFelt('radio', 'primaer_kontakt_index'));

        const handlingCelle = document.createElement('td');
        const fjernKnap = document.createElement('button');
        fjernKnap.type = 'button';
        fjernKnap.className = 'fjern-raekke link-knap';
        fjernKnap.textContent = 'Fjern';
        handlingCelle.appendChild(fjernKnap);

        raekke.append(navnCelle, stillingCelle, telefonCelle, emailCelle, primaerCelle, handlingCelle);
        tabelKrop.appendChild(raekke);
        genberegnPrimaerIndekser();
    });

    tabelKrop.addEventListener('click', function (event) {
        if (event.target.classList.contains('fjern-raekke')) {
            event.target.closest('tr').remove();
            genberegnPrimaerIndekser();
        }
    });

    genberegnPrimaerIndekser();
}

function lavTabtAarsagToggle() {
    const vaelg = document.getElementById('salgsresultat-vaelg');
    const felter = document.getElementById('tabt-aarsag-felter');
    if (!vaelg || !felter) return;

    function opdater() {
        felter.hidden = vaelg.value !== 'Tabt';
    }
    vaelg.addEventListener('change', opdater);
    opdater();
}
