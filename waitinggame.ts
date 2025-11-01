type Response = 'accepted' | 'rejected' | 'ignored';

async function paramdam(msg: string): Promise<Response> {
    const choices: Response[] = ['ignored', 'accepted', 'rejected'];
    return choices[Math.floor(Math.random() * choices.length)];
}

async function sendFlowers(): Promise<'flowers_sent'> {
    return 'flowers_sent';
}

function createDate(year: number, month: number, day: number): Date {
    return new Date(year, month - 1, day);
}

function isSameDate(d1: Date, year: number, month: number, day: number): boolean {
    return d1.getFullYear() === year && d1.getMonth() + 1 === month && d1.getDate() === day;
}

async function fetchHerSoul(today: Date): Promise<{status: Response | 'waiting'; attempts?: number; daysUntilNext?: number}> {
    if (!isSameDate(today, 2025, 11, 1)) {
        return { status: 'ignored' };
    }

    let attempts = 0;
    let response: Response;
    
    do {
        response = await paramdam("Paramdam: Notice me 😭");
        attempts++;
    } while (response === 'ignored' && attempts < 3);

    if (response === 'accepted') {
        await sendFlowers();
        return { status: 'accepted', attempts };
    }

    const valentineDate = createDate(2026, 2, 14);
    const daysUntilNext = Math.ceil((valentineDate.getTime() - today.getTime()) / 86400000);

    return { status: 'waiting', attempts, daysUntilNext };
}

// Usage
(async () => {
    const result = await fetchHerSoul(createDate(2025, 11, 1));
    result;
})();