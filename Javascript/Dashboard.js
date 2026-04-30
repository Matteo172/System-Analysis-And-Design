document.addEventListener('DOMContentLoaded', function() {
    const calendarDates = document.getElementById('calendarDates');
    const monthYearText = document.getElementById('monthYearText');
    let date = new Date();

    function renderCalendar() {
        // Keep the day headers (Sun-Sat)
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
            .map(day => `<div class="day-name">${day}</div>`).join('');
        
        calendarDates.innerHTML = "";

        const month = date.getMonth();
        const year = date.getFullYear();

        monthYearText.innerText = new Intl.DateTimeFormat('en-US', { 
            month: 'long', 
            year: 'numeric' 
        }).format(date);

        const firstDay = new Date(year, month, 1).getDay();
        const lastDay = new Date(year, month + 1, 0).getDate();

        // Add empty spaces for previous month
        for (let i = 0; i < firstDay; i++) {
            const emptyDiv = document.createElement('div');
            calendarDates.appendChild(emptyDiv);
        }

        // Add actual days
        for (let i = 1; i <= lastDay; i++) {
            const dateDiv = document.createElement('div');
            dateDiv.classList.add('calendar-date');
            dateDiv.innerText = i;
            
            if (i === new Date().getDate() && 
                month === new Date().getMonth() && 
                year === new Date().getFullYear()) {
                dateDiv.classList.add('current-day');
            }
            
            calendarDates.appendChild(dateDiv);
        }
    }

    document.getElementById('prevMonth').addEventListener('click', () => {
        date.setMonth(date.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        date.setMonth(date.getMonth() + 1);
        renderCalendar();
    });

    renderCalendar();
});