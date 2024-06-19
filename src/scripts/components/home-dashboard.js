export default function HomeDashboard(el, {
    prevMonth,
    nextMonth,
    prevWeek,
    nextWeek,
}) {
    const [
        currentMonth,
        currentWeek,
    ] = el.querySelectorAll('div > nav > span');
    const [
        prevActivities,
        nextActivities,
        prevReminders,
        nextReminders,
    ] = el.querySelectorAll('div > nav > button');
    const [
        activities,
        reminders,
    ] = [el.firstElementChild, el.lastElementChild];

    function getActivitiesByMonth(month) {
        fetch(`/json/activities-listing?${new URLSearchParams({
            month,
        })}`, {
            headers: { Accept: 'application/json' },
        })
            .then(res => res.json().then(json => ({
                status: res.status,
                ...json,
            })))
            .then(({
                status,
                markup = '',
                prevMonth: p = '',
                nextMonth: n = '',
            }) => {
                if (status !== 200) return;

                prevMonth = p;
                nextMonth = n;
                currentMonth.textContent = month.toUpperCase;
                activities.querySelector('ul').outerHTML = markup.trim();
            });
    }
    function getRemindersByWeek(week) {
        fetch(`/json/reminders-listing?${new URLSearchParams({
            week,
        })}`, {
            headers: { Accept: 'application/json' },
        })
            .then(res => res.json().then(json => ({
                status: res.status,
                ...json,
            })))
            .then(({
                status,
                markup = '',
                prevWeek: p = '',
                nextWeek: n = '',
            }) => {
                if (status !== 200) return;

                prevWeek = p;
                nextWeek = n;
                currentWeek.textContent = week;
                reminders.querySelector('ul').outerHTML = markup.trim();
            });
    }

    prevActivities.onclick = () => {
        getActivitiesByMonth(prevMonth);
    };
    nextActivities.onclick = () => {
        getActivitiesByMonth(nextMonth);
    };
    prevReminders.onclick = () => {
        getRemindersByWeek(prevWeek);
    };
    nextReminders.onclick = () => {
        getRemindersByWeek(nextWeek);
    };
}
