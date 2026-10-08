// Appointment-booking calendar block: fetches available slots, renders a
// month grid + time-slot picker, and submits a booking. One instance per
// [data-calendar-widget] element (normally just one per page).
(function () {
    var MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    var DAYS = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toDateKey(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

    function initWidget(root) {
        var csrfToken = root.getAttribute('data-csrf') || '';
        var byDate = {};
        var today = new Date();
        var viewYear = today.getFullYear();
        var viewMonth = today.getMonth();
        var selectedDate = null;
        var selectedTime = null;

        function todayKey() { return toDateKey(today.getFullYear(), today.getMonth(), today.getDate()); }

        function loadAvailability() {
            root.innerHTML = '<p class="calendar-loading">Beschikbare momenten laden…</p>';
            fetch('/calendar-availability.php')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    byDate = {};
                    (data.slots || []).forEach(function (slot) {
                        if (!byDate[slot.date]) byDate[slot.date] = [];
                        byDate[slot.date].push(slot.time);
                    });
                    selectedDate = null;
                    selectedTime = null;
                    render();
                })
                .catch(function () {
                    root.innerHTML = '<p class="calendar-loading">Kon de beschikbare momenten niet laden. Herlaad de pagina.</p>';
                });
        }

        function monthGrid() {
            var firstOfMonth = new Date(viewYear, viewMonth, 1);
            var startWeekday = (firstOfMonth.getDay() + 6) % 7; // Monday = 0
            var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

            var cells = '';
            for (var i = 0; i < startWeekday; i++) cells += '<div></div>';
            for (var d = 1; d <= daysInMonth; d++) {
                var key = toDateKey(viewYear, viewMonth, d);
                var hasSlots = byDate[key] && byDate[key].length > 0;
                var isPast = key < todayKey();
                var classes = ['cal-day'];
                if (key === selectedDate) classes.push('is-selected');
                if (!hasSlots || isPast) classes.push('is-disabled');
                cells += '<button type="button" class="' + classes.join(' ') + '" data-date="' + key + '"' + (hasSlots && !isPast ? '' : ' disabled') + '>' + d + '</button>';
            }

            return (
                '<div class="cal-header">' +
                    '<button type="button" class="cal-nav" data-nav="-1" aria-label="Vorige maand">&lsaquo;</button>' +
                    '<span class="cal-month-label">' + MONTHS[viewMonth] + ' ' + viewYear + '</span>' +
                    '<button type="button" class="cal-nav" data-nav="1" aria-label="Volgende maand">&rsaquo;</button>' +
                '</div>' +
                '<div class="cal-weekdays">' + DAYS.map(function (d) { return '<div>' + d + '</div>'; }).join('') + '</div>' +
                '<div class="cal-days">' + cells + '</div>'
            );
        }

        function timePicker() {
            if (!selectedDate) return '';
            var times = byDate[selectedDate] || [];
            var dateObj = new Date(selectedDate + 'T00:00:00');
            var label = dateObj.toLocaleDateString('nl-BE', { weekday: 'long', day: 'numeric', month: 'long' });

            return (
                '<div class="cal-times">' +
                    '<div class="cal-times-label">Beschikbaar op ' + label + '</div>' +
                    '<div class="cal-time-list">' +
                        times.map(function (t) {
                            return '<button type="button" class="cal-time' + (t === selectedTime ? ' is-selected' : '') + '" data-time="' + t + '">' + t + '</button>';
                        }).join('') +
                    '</div>' +
                '</div>'
            );
        }

        function privacyNote() {
            var url = document.body.getAttribute('data-privacy-url') || '';
            if (!url) return '';
            return '<p class="form-privacy-note">Door te verzenden ga je akkoord met ons <a href="' + url + '">privacybeleid</a>.</p>';
        }

        function bookingForm() {
            if (!selectedDate || !selectedTime) return '';
            return (
                '<form class="cal-form" data-cal-form>' +
                    '<div style="position:absolute;left:-9999px;" aria-hidden="true">' +
                        '<label for="cal-website">Website</label>' +
                        '<input type="text" id="cal-website" name="website" tabindex="-1" autocomplete="off">' +
                    '</div>' +
                    '<label for="cal-name">Naam</label>' +
                    '<input type="text" id="cal-name" name="name" required>' +
                    '<label for="cal-email">E-mailadres</label>' +
                    '<input type="email" id="cal-email" name="email" required>' +
                    '<label for="cal-message">Bericht (optioneel)</label>' +
                    '<textarea id="cal-message" name="message" rows="3"></textarea>' +
                    privacyNote() +
                    '<button type="submit">Bevestig afspraak</button>' +
                    '<p class="cal-form-status" data-cal-status></p>' +
                '</form>'
            );
        }

        function render() {
            root.innerHTML =
                '<div class="cal-grid">' + monthGrid() + '</div>' +
                '<div class="cal-side">' + timePicker() + bookingForm() + '</div>';
        }

        root.addEventListener('click', function (e) {
            var nav = e.target.closest('[data-nav]');
            if (nav) {
                viewMonth += parseInt(nav.getAttribute('data-nav'), 10);
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                render();
                return;
            }

            var dayBtn = e.target.closest('.cal-day');
            if (dayBtn && !dayBtn.disabled) {
                selectedDate = dayBtn.getAttribute('data-date');
                selectedTime = null;
                render();
                return;
            }

            var timeBtn = e.target.closest('.cal-time');
            if (timeBtn) {
                selectedTime = timeBtn.getAttribute('data-time');
                render();
            }
        });

        root.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-cal-form]');
            if (!form) return;
            e.preventDefault();

            var status = form.querySelector('[data-cal-status]');
            var submitBtn = form.querySelector('button[type="submit"]');
            status.textContent = 'Bezig met versturen…';
            status.classList.remove('is-error');
            submitBtn.disabled = true;

            var formData = new FormData(form);
            formData.append('csrf_token', csrfToken);
            formData.append('date', selectedDate);
            formData.append('time', selectedTime);

            fetch('/book-slot.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
                .then(function (result) {
                    var data = result.data;
                    if (!data || !data.ok) {
                        status.textContent = (data && data.error) || 'Boeken is mislukt. Probeer opnieuw.';
                        status.classList.add('is-error');
                        submitBtn.disabled = false;
                        if (result.status === 409) {
                            // Slot was taken by someone else in the meantime — refresh the grid.
                            loadAvailability();
                        }
                        return;
                    }
                    root.innerHTML = '<p class="cal-confirmed">Afspraak bevestigd — tot dan!</p>';
                })
                .catch(function () {
                    status.textContent = 'Boeken is mislukt door een netwerk- of serverfout.';
                    status.classList.add('is-error');
                    submitBtn.disabled = false;
                });
        });

        loadAvailability();
    }

    document.querySelectorAll('[data-calendar-widget]').forEach(initWidget);
})();
