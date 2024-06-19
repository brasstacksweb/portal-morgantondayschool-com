const getFirstOfWeek = date => {
    const day = date.getDay();
    const diff = date.getDate() - day;

    return new Date(date.setDate(diff));
};

const getFirstOfMonth = date => new Date(date.getFullYear(), date.getMonth(), 1);

export default function HomeDashboard(el, {
    prevMonth,
    nextMonth,
    prevWeek,
    nextWeek,
    loadingClass = 'is-loading',
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
        activities.classList.add(loadingClass);
        prevActivities.disabled = true;
        nextActivities.disabled = true;

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

                const firstOfMonth = getFirstOfMonth(new Date(month));

                activities.classList.remove(loadingClass);
                prevActivities.disabled = false;
                nextActivities.disabled = false;
                prevMonth = p;
                nextMonth = n;
                currentMonth.textContent = `${firstOfMonth.toLocaleString('default', { month: 'long' })} ${firstOfMonth.getFullYear()}`;
                activities.querySelector('ul').outerHTML = markup.trim();
            });
    }
    function getRemindersByWeek(week) {
        reminders.classList.add(loadingClass);
        prevReminders.disabled = true;
        nextReminders.disabled = true;

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

                const firstOfWeek = getFirstOfWeek(new Date(week));

                reminders.classList.remove(loadingClass);
                prevReminders.disabled = false;
                nextReminders.disabled = false;
                prevWeek = p;
                nextWeek = n;
                currentWeek.textContent = `Week of ${firstOfWeek.toLocaleDateString()}`;
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
