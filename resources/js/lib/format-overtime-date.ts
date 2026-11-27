const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});

export function formatOvertimeDate(date: string): string {
    return dateFormatter.format(new Date(`${date}T00:00:00+07:00`));
}

export function formatOvertimeEnd(date: string, end: string): string {
    const endDate = end.slice(0, 10);
    const time = end.slice(11, 16);

    return endDate === date ? time : `${time} (${formatOvertimeDate(endDate)})`;
}
