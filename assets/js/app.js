$(function () {
    $('.datatable').DataTable({
        responsive: true,
        pageLength: 8,
        order: [],
        dom: 'Bfrtip',
        buttons: ['excelHtml5']
    });

    $('[data-calendar]').each(function () {
        const events = JSON.parse(this.dataset.events || '[]');
        renderCalendar(this, events);
    });

    document.querySelectorAll('[data-wire-category]').forEach((select) => {
        const syncWireFields = () => {
            const targetId = select.dataset.wireCategory;
            const wrapper = document.getElementById(targetId);
            if (!wrapper) {
                return;
            }

            const selectedOption = select.options[select.selectedIndex];
            const allowsWireLength = selectedOption?.dataset.allowsWireLength === '1';
            wrapper.classList.toggle('d-none', !allowsWireLength);
            wrapper.querySelectorAll('input, select').forEach((field) => {
                if (!allowsWireLength && field.matches('input')) {
                    field.value = '';
                }
            });
        };

        syncWireFields();
        select.addEventListener('change', syncWireFields);
    });
});

function renderCalendar(container, events) {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const eventMap = {};

    events.forEach((event) => {
        eventMap[event.event_date] = eventMap[event.event_date] || [];
        eventMap[event.event_date].push(event);
    });

    let html = '<div class="d-flex justify-content-between mb-3"><h5 class="mb-0">Booking Calendar</h5><span class="text-muted">' +
        now.toLocaleString('en-US', { month: 'long', year: 'numeric' }) +
        '</span></div>';
    html += '<div class="calendar-grid">';

    for (let i = 0; i < firstDay.getDay(); i++) {
        html += '<div class="calendar-cell"></div>';
    }

    for (let day = 1; day <= lastDay.getDate(); day++) {
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayEvents = eventMap[dateKey] || [];
        html += `<div class="calendar-cell ${dayEvents.length ? 'has-event calendar-clickable' : ''}" ${dayEvents.length ? `data-calendar-date="${dateKey}"` : ''}>
            <div class="calendar-day">${day}</div>
            ${dayEvents.slice(0, 2).map((item) => `<div class="calendar-event" title="${escapeHtml(item.event_name)}">${escapeHtml(item.event_name)}</div>`).join('')}
            ${dayEvents.length > 2 ? `<div class="calendar-more">+${dayEvents.length - 2} more</div>` : ''}
        </div>`;
    }

    html += '</div>';
    container.innerHTML = html;

    container.querySelectorAll('[data-calendar-date]').forEach((cell) => {
        cell.addEventListener('click', () => openCalendarEventsModal(cell.dataset.calendarDate, eventMap[cell.dataset.calendarDate] || []));
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function openCalendarEventsModal(dateKey, events) {
    let modal = document.getElementById('calendarEventsModal');

    if (!modal) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="calendarEventsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Events</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(wrapper.firstElementChild);
        modal = document.getElementById('calendarEventsModal');
    }

    modal.querySelector('.modal-title').textContent = `Events on ${formatCalendarDate(dateKey)}`;
    modal.querySelector('.modal-body').innerHTML = events.map((event) => `
        <div class="calendar-modal-item">
            <div class="calendar-modal-title">${escapeHtml(event.event_name || 'Event')}</div>
            ${event.event_type ? `<div class="calendar-modal-meta"><strong>Type:</strong> ${escapeHtml(event.event_type)}</div>` : ''}
            ${(event.start_time || event.end_time) ? `<div class="calendar-modal-meta"><strong>Time:</strong> ${formatTime(event.start_time)}${event.end_time ? ` - ${formatTime(event.end_time)}` : ''}</div>` : ''}
            ${event.package_name ? `<div class="calendar-modal-meta"><strong>Package:</strong> ${escapeHtml(event.package_name)}</div>` : ''}
            ${event.customer_name ? `<div class="calendar-modal-meta"><strong>Customer:</strong> ${escapeHtml(event.customer_name)}</div>` : ''}
            ${event.full_name ? `<div class="calendar-modal-meta"><strong>Name:</strong> ${escapeHtml(event.full_name)}</div>` : ''}
            ${event.assigned_employees ? `<div class="calendar-modal-meta"><strong>Assigned Crew:</strong> ${escapeHtml(event.assigned_employees)}</div>` : ''}
            ${event.address ? `<div class="calendar-modal-meta"><strong>Location:</strong> ${escapeHtml(event.address)}</div>` : ''}
            ${event.event_status ? `<div class="calendar-modal-meta"><strong>Status:</strong> ${escapeHtml(event.event_status)}</div>` : ''}
        </div>
    `).join('');

    const instance = bootstrap.Modal.getOrCreateInstance(modal);
    instance.show();
}

function formatCalendarDate(dateKey) {
    const date = new Date(`${dateKey}T00:00:00`);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatTime(timeValue) {
    if (!timeValue) {
        return '';
    }

    const date = new Date(`1970-01-01T${timeValue}`);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}
