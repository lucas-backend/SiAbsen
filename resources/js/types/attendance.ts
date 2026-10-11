export type AttendanceRecordStatus =
    | 'HADIR'
    | 'TERLAMBAT'
    | 'TIDAK_HADIR'
    | 'IZIN'
    | 'SAKIT';

export type LocationStatus =
    | 'SESUAI'
    | 'DI_LUAR_RADIUS'
    | 'TIDAK_TERVERIFIKASI';

export type AttendanceRecord = {
    id: number;
    session_id: number;
    student_id: number;
    status: AttendanceRecordStatus;
    late_minutes: number | null;
    scanned_at: string;
    latitude: number | null;
    longitude: number | null;
    distance_meters: number | null;
    location_status: LocationStatus | null;
};

export type AttendanceSession = {
    id: number;
    assignment_id: number;
    class_id: number;
    class_name: string | null;
    session_date: string;
    start_at: string;
    end_at: string;
    qr_payload: string;
    created_by: number;
    latitude: number | null;
    longitude: number | null;
};

export type AttendanceParticipant = {
    student_id: number;
    name: string;
    student_number: string | null;
    status: AttendanceRecordStatus;
    late_minutes: number | null;
    scanned_at: string | null;
    location_status: LocationStatus | null;
    distance_meters: number | null;
};

export type TeacherAssignmentOption = {
    id: number;
    class_id: number;
    class_name: string | null;
    subject_id: number;
    subject_name: string | null;
};

export type StudentAttendanceHistoryItem = {
    session_id: number;
    session_date: string;
    start_at: string;
    end_at: string;
    status: AttendanceRecordStatus;
    late_minutes: number | null;
    scanned_at: string | null;
    location_status: LocationStatus | null;
    distance_meters: number | null;
};
