import type { AttendanceRecordStatus, LocationStatus } from './attendance';

export type ReportFilterOption = {
    id: number;
    name: string;
};

export type ReportStatusOption = {
    value: AttendanceRecordStatus;
    label: string;
};

export type ReportFilters = {
    education_level_id: string;
    class_id: string;
    subject_id: string;
    student_id: string;
    date_from: string;
    date_to: string;
    status: string;
};

export type ReportRow = {
    session_id: number;
    session_date: string;
    start_at: string;
    class_id: number;
    class_name: string | null;
    subject_id: number | null;
    subject_name: string | null;
    student_id: number;
    student_name: string;
    student_number: string | null;
    status: AttendanceRecordStatus;
    status_label: string;
    late_minutes: number | null;
    scanned_at: string | null;
    location_status: LocationStatus | null;
    location_label: string | null;
    distance_meters: number | null;
    latitude: number | null;
    longitude: number | null;
};

export type ReportSummary = {
    hadir: number;
    terlambat: number;
    tidak_hadir: number;
    izin: number;
    sakit: number;
    total: number;
    kehadiran: number;
    persentase_kehadiran: number;
};

export type ReportResult = {
    rows: ReportRow[];
    summary: ReportSummary;
};

export type AcademicClassOption = {
    id: number;
    name: string;
    education_level_id: number | null;
};

export type AcademicStudentOption = {
    id: number;
    name: string;
    student_number: string | null;
    class_id: number;
    class_name: string;
};
