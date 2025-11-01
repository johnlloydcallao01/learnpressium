type Response = 'accepted' | 'rejected' | 'ignored';
const paramdam = async (msg: string): Promise<Response> =>
    (['ignored','accepted','rejected'] as const)[Math.floor(Math.random()*3)];
const sendFlowers = async (): Promise<'flowers_sent'> => 'flowers_sent';
const createDate = (y: number, m: number, d: number) => new Date(y, m-1, d);
const isSameDate = (d: Date, y: number, m: number, day: number) =>
    d.getFullYear() === y && d.getMonth()+1 === m && d.getDate() === day;
const fetchHerSoul = async (today: Date) => {
    if (!isSameDate(today, 2025, 11, 1)) return { status: 'ignored' };
    let attempts = 0; 
    let res: Response;
    do { res = await paramdam("Paramdam: Notice me 😭🕯"); attempts++; } 
    while (res==='ignored' && attempts<3);
    if (res==='accepted') { await sendFlowers(); return { status:'accepted', attempts }; }
    const valentine = createDate(2026,2,14);
    return { status:'waiting', attempts, daysUntilNext: Math.ceil((valentine.getTime()-today.getTime())/86400000) };
}
(async()=>fetchHerSoul(createDate(2025,11,1)))();