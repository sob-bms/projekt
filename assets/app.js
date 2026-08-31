document.addEventListener('DOMContentLoaded', function () {
    lavVirksomhedTabel();
    lavKontaktTabel();
    lavTabtAarsagToggle();
    lavTabelTopScroll();
    lavKolonneVisning();
});

/**
 * Spejler tabellens vandrette scroll i en tynd bjælke oven over den, så man
 * også kan scrolle en bred tabel fra toppen uden at skulle ned til bunden af
 * siden først.
 */
function lavTabelTopScroll() {
    const top = document.getElementById('tabel-scroll-top');
    const bund = document.getElementById('tabel-scroll');
    const tabel = document.getElementById('data-tabel');
    if (!top || !bund || !tabel) return;

    const indre = top.firstElementChild;

    function opdaterBredde() {
        indre.style.width = tabel.scrollWidth + 'px';
    }
    opdaterBredde();
    window.addEventListener('resize', opdaterBredde);

    let synkroniserer = false;
    top.addEventListener('scroll', function () {
        if (synkroniserer) return;
        synkroniserer = true;
        bund.scrollLeft = top.scrollLeft;
        synkroniserer = false;
    });
    bund.addEventListener('scroll', function () {
        if (synkroniserer) return;
        synkroniserer = true;
        top.scrollLeft = bund.scrollLeft;
        synkroniserer = false;
    });
}

/**
 * Vis/skjul kolonner-panel for projektlisten. Hvilke kolonner der er skjult
 * huskes pr. browser i localStorage, så valget består ved næste besøg.
 */
function lavKolonneVisning() {
    const knap = document.getElementById('kolonne-vaelger-knap');
    const panel = document.getElementById('kolonne-vaelger-panel');
    if (!knap || !panel) return;

    const LAGER_NOEGLE = 'bms-projekter-skjulte-kolonner';

    function hentSkjulte() {
        try {
            const gemt = JSON.parse(localStorage.getItem(LAGER_NOEGLE) || '[]');
            return Array.isArray(gemt) ? gemt : [];
        } catch (e) {
            return [];
        }
    }

    function gemSkjulte(liste) {
        try {
            localStorage.setItem(LAGER_NOEGLE, JSON.stringify(liste));
        } catch (e) {
            // localStorage utilgængelig (privat vinduesbrowsing e.l.) - ignorér.
        }
    }

    function visKolonne(kolonne, vis) {
        document.querySelectorAll('[data-kolonne="' + kolonne + '"]').forEach(function (celle) {
            celle.hidden = !vis;
        });
    }

    const skjulte = hentSkjulte();
    panel.querySelectorAll('input[data-kolonne-toggle]').forEach(function (checkbox) {
        const kolonne = checkbox.dataset.kolonneToggle;
        const erSkjult = skjulte.includes(kolonne);
        checkbox.checked = !erSkjult;
        visKolonne(kolonne, !erSkjult);

        checkbox.addEventListener('change', function () {
            const nu = hentSkjulte().filter(function (k) { return k !== kolonne; });
            if (!checkbox.checked) nu.push(kolonne);
            gemSkjulte(nu);
            visKolonne(kolonne, checkbox.checked);
            window.dispatchEvent(new Event('resize'));
        });
    });

    knap.addEventListener('click', function (event) {
        event.stopPropagation();
        const aabnes = panel.hidden;
        panel.hidden = !aabnes;
        knap.setAttribute('aria-expanded', String(aabnes));
    });

    document.addEventListener('click', function (event) {
        if (!panel.hidden && !panel.contains(event.target) && event.target !== knap) {
            panel.hidden = true;
            knap.setAttribute('aria-expanded', 'false');
        }
    });
}

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
