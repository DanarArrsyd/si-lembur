const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

export function formatDate(date: string) {
    return dateFormatter.format(new Date(`${date}T00:00:00Z`));
}

export function formatEndTime(date: string, endAt: string) {
    const endDate = endAt.slice(0, 10);
    const time = endAt.slice(11);

    return endDate === date ? time : `${time} · ${formatDate(endDate)}`;
}
