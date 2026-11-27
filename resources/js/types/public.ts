/** Karyawan aktif dari GET /api/departments/{id}/employees (hanya 3 field ini). */
export type EmployeeOption = {
    id: number;
    nik: string;
    name: string;
};

export type DateChoice = {
    value: 'TODAY' | 'YESTERDAY';
    label: string;
    date: string;
};

export type PublicOvertimeFormProps = {
    departments: { id: number; name: string }[];
    dateChoices: DateChoice[];
    serverTime: string;
    maxGrossMinutes: number;
};

export type OvertimeStatus = 'PENDING' | 'APPROVED' | 'REJECTED';

export type OvertimeDetails = {
    date: string;
    start_at: string;
    end_at: string;
    break_minutes: number;
    total_minutes: number;
    day_type: 'HARI_KERJA' | 'HARI_LIBUR';
    employee: Pick<EmployeeOption, 'name' | 'nik'>;
    department: string;
    description: string;
};

export type OvertimeReview = OvertimeDetails & {
    exceeds_daily_limit: boolean;
    exceeds_weekly_limit: boolean;
    daily_limit_minutes: number;
    weekly_limit_minutes: number;
};

export type PublicStatusProps = {
    departments: PublicOvertimeFormProps['departments'];
    prefillDepartmentId: number | null;
    prefillEmployee: EmployeeOption | null;
};

export type StatusEntry = {
    number: string;
    date: string;
    start: string;
    end: string;
    total_minutes: number;
    status: OvertimeStatus;
    status_label: string;
    reject_reason: string | null;
    can_resubmit: boolean;
};
