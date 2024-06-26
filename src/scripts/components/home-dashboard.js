const getFirstOfWeek = date => {
    const day = date.getDay();
    const diff = date.getDate() - day;

    return new Date(date.setDate(diff));
};

export default function HomeDashboard(el, {
    prevWeek,
    nextWeek,
    loadingClass = 'is-loading',
}) {
    const currentWeek = el.querySelector('div > nav > span');
    const [
        prevReminders,
        nextReminders,
    ] = el.querySelectorAll('div > nav > button');
    const reminders = el.firstElementChild;

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

    prevReminders.onclick = () => {
        getRemindersByWeek(prevWeek);
    };
    nextReminders.onclick = () => {
        getRemindersByWeek(nextWeek);
    };
}
