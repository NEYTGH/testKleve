// Benutzer-Daten (später durch API/Datenbank zu ersetzen)
const benutzer = [
    { id: 1, name: 'Maria Schmidt' },
    { id: 2, name: 'Thomas Müller' },
    { id: 3, name: 'Anna Wagner' },
    { id: 4, name: 'Michael Weber' },
    { id: 5, name: 'Lisa Becker' }
];

// Simulierte Reservierungen (später durch Datenbank zu ersetzen)
let reservierungen = [];

// DOM-Elemente
const form = document.getElementById('reservierungForm');
const successMessage = document.getElementById('success-message');
const errorMessage = document.getElementById('error-message');
const errorText = document.getElementById('error-text');
const reservationInfo = document.getElementById('reservation-info');
const nameSelect = document.getElementById('name');

// Initialisierung
document.addEventListener('DOMContentLoaded', () => {
    // Aktuelles Jahr im Footer
    document.getElementById('year').textContent = new Date().getFullYear();
    
    // Benutzer in Select-Element laden
    benutzer.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.name;
        nameSelect.appendChild(option);
    });

    // Minimales Datum auf heute setzen
    const datumInput = document.getElementById('datum');
    const heute = new Date().toISOString().split('T')[0];
    datumInput.min = heute;
    datumInput.value = heute;
});

// Hilfsfunktionen
function formatDate(date) {
    return new Date(date).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatTime(time) {
    return time;
}

// Verfügbarkeitsprüfung
async function pruefeVerfuegbarkeit(daten) {
    // Simuliere Serveranfrage
    await new Promise(resolve => setTimeout(resolve, 800));

    const datum = new Date(daten.datum);
    const startZeit = new Date(datum.setHours(...daten.startZeit.split(':'), 0, 0));
    const endZeit = new Date(datum.setHours(...daten.endZeit.split(':'), 0, 0));

    // Prüfe Überschneidungen
    const ueberschneidung = reservierungen.some(reservierung => {
        const gleichesDatum = new Date(reservierung.datum).toDateString() === new Date(daten.datum).toDateString();
        
        if (!gleichesDatum) return false;

        const resStart = new Date(reservierung.datum + 'T' + reservierung.startZeit);
        const resEnd = new Date(reservierung.datum + 'T' + reservierung.endZeit);

        return (startZeit >= resStart && startZeit < resEnd) ||
               (endZeit > resStart && endZeit <= resEnd) ||
               (startZeit <= resStart && endZeit >= resEnd);
    });

    // Zufällig manchmal nicht verfügbar (20% Chance)
    const zufaelligNichtVerfuegbar = Math.random() < 0.2;

    return !ueberschneidung && !zufaelligNichtVerfuegbar;
}

// Reservierung erstellen
async function erstelleReservierung(daten) {
    // Simuliere Serveranfrage
    await new Promise(resolve => setTimeout(resolve, 1000));

    try {
        reservierungen.push(daten);
        return { erfolg: true };
    } catch (error) {
        console.error('Fehler beim Speichern:', error);
        return { erfolg: false };
    }
}

// Formular zurücksetzen
function resetForm() {
    form.reset();
    successMessage.style.display = 'none';
    errorMessage.style.display = 'none';
    form.style.display = 'block';
    
    // Datum auf heute setzen
    const datumInput = document.getElementById('datum');
    datumInput.value = new Date().toISOString().split('T')[0];
}

// Erfolgreiche Reservierung anzeigen
function zeigeErfolg(daten) {
    const benutzerName = benutzer.find(u => u.id === parseInt(daten.benutzerId)).name;
    
    const details = `
        <div class="detail-item">
            <span class="label">Datum:</span>
            <span>${formatDate(daten.datum)}</span>
        </div>
        <div class="detail-item">
            <span class="label">Zeit:</span>
            <span>${formatTime(daten.startZeit)} - ${formatTime(daten.endZeit)}</span>
        </div>
        <div class="detail-item">
            <span class="label">Name:</span>
            <span>${benutzerName}</span>
        </div>
        <div class="detail-item">
            <span class="label">Anlass:</span>
            <span>${daten.anlass}</span>
        </div>
        <div class="detail-item">
            <span class="label">Externe Teilnehmer:</span>
            <span>${daten.externeTeilnehmer ? 'Ja' : 'Nein'}</span>
        </div>
        ${daten.bemerkung ? `
        <div class="detail-item">
            <span class="label">Bemerkung:</span>
            <span>${daten.bemerkung}</span>
        </div>
        ` : ''}
    `;
    
    reservationInfo.innerHTML = details;
    form.style.display = 'none';
    successMessage.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Formular-Handler
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = {
        datum: document.getElementById('datum').value,
        startZeit: document.getElementById('startZeit').value,
        endZeit: document.getElementById('endZeit').value,
        benutzerId: document.getElementById('name').value,
        anlass: document.getElementById('anlass').value,
        externeTeilnehmer: document.getElementById('externeTeilnehmer').checked,
        bemerkung: document.getElementById('bemerkung').value
    };

    // Validierung
    if (formData.startZeit >= formData.endZeit) {
        errorText.textContent = 'Die Startzeit muss vor der Endzeit liegen.';
        errorMessage.style.display = 'block';
        return;
    }

    try {
        const verfuegbar = await pruefeVerfuegbarkeit(formData);
        
        if (verfuegbar) {
            const ergebnis = await erstelleReservierung(formData);
            if (ergebnis.erfolg) {
                zeigeErfolg(formData);
                errorMessage.style.display = 'none';
            } else {
                throw new Error('Speichern fehlgeschlagen');
            }
        } else {
            errorText.textContent = 'Der Raum ist im gewählten Zeitraum leider nicht verfügbar.';
            errorMessage.style.display = 'block';
        }
    } catch (error) {
        console.error(error);
        errorText.textContent = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
        errorMessage.style.display = 'block';
    }
});