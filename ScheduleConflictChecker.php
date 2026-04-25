<?php
/**
 * Schedule Conflict Checker for Class Assignments
 * Checks for room and teacher availability conflicts
 */

class ScheduleConflictChecker {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check for scheduling conflicts
     */
    public function checkConflicts($courseId, $teacherId, $roomId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $scheduleId = null) {
        $conflicts = [];
        
        // Check teacher availability
        $teacherConflicts = $this->checkTeacherConflict($teacherId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $scheduleId);
        if (!empty($teacherConflicts)) {
            $conflicts['teacher'] = $teacherConflicts;
        }
        
        // Check room availability
        $roomConflicts = $this->checkRoomConflict($roomId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $scheduleId);
        if (!empty($roomConflicts)) {
            $conflicts['room'] = $roomConflicts;
        }
        
        // Check course conflicts (same course at same time)
        $courseConflicts = $this->checkCourseConflict($courseId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $scheduleId);
        if (!empty($courseConflicts)) {
            $conflicts['course'] = $courseConflicts;
        }
        
        return $conflicts;
    }
    
    /**
     * Check if teacher is available at the specified time
     */
    private function checkTeacherConflict($teacherId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $excludeScheduleId = null) {
        $sql = "SELECT cs.*, c.course_code, c.course_name 
                FROM class_schedules cs
                JOIN courses c ON cs.course_id = c.id
                WHERE cs.teacher_id = :teacher_id 
                AND cs.day_of_week = :day_of_week 
                AND cs.semester = :semester 
                AND cs.academic_year = :academic_year 
                AND cs.is_active = 1
                AND (
                    (cs.start_time < :end_time AND cs.end_time > :start_time)
                )";
        
        $params = [
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        if ($excludeScheduleId) {
            $sql .= " AND cs.id != :exclude_id";
            $params['exclude_id'] = $excludeScheduleId;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Check if room is available at the specified time
     */
    private function checkRoomConflict($roomId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $excludeScheduleId = null) {
        $sql = "SELECT cs.*, c.course_code, c.course_name,
                       r.room_number, r.building
                FROM class_schedules cs
                JOIN courses c ON cs.course_id = c.id
                JOIN rooms r ON cs.room_id = r.id
                WHERE cs.room_id = :room_id 
                AND cs.day_of_week = :day_of_week 
                AND cs.semester = :semester 
                AND cs.academic_year = :academic_year 
                AND cs.is_active = 1
                AND (
                    (cs.start_time < :end_time AND cs.end_time > :start_time)
                )";
        
        $params = [
            'room_id' => $roomId,
            'day_of_week' => $dayOfWeek,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        if ($excludeScheduleId) {
            $sql .= " AND cs.id != :exclude_id";
            $params['exclude_id'] = $excludeScheduleId;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Check if course is already scheduled at the specified time
     */
    private function checkCourseConflict($courseId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $excludeScheduleId = null) {
        $sql = "SELECT cs.*, r.room_number, r.building
                FROM class_schedules cs
                JOIN rooms r ON cs.room_id = r.id
                WHERE cs.course_id = :course_id 
                AND cs.day_of_week = :day_of_week 
                AND cs.semester = :semester 
                AND cs.academic_year = :academic_year 
                AND cs.is_active = 1
                AND (
                    (cs.start_time < :end_time AND cs.end_time > :start_time)
                )";
        
        $params = [
            'course_id' => $courseId,
            'day_of_week' => $dayOfWeek,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        if ($excludeScheduleId) {
            $sql .= " AND cs.id != :exclude_id";
            $params['exclude_id'] = $excludeScheduleId;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get available rooms for a specific time slot
     */
    public function getAvailableRooms($dayOfWeek, $startTime, $endTime, $semester, $academicYear, $excludeScheduleId = null, $minCapacity = null) {
        $sql = "SELECT r.* FROM rooms r
                WHERE r.is_active = 1
                AND r.id NOT IN (
                    SELECT cs.room_id 
                    FROM class_schedules cs
                    WHERE cs.day_of_week = :day_of_week 
                    AND cs.semester = :semester 
                    AND cs.academic_year = :academic_year 
                    AND cs.is_active = 1
                    AND (cs.start_time < :end_time AND cs.end_time > :start_time)";
        
        $params = [
            'day_of_week' => $dayOfWeek,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        if ($excludeScheduleId) {
            $sql .= " AND cs.id != :exclude_id";
            $params['exclude_id'] = $excludeScheduleId;
        }
        
        $sql .= ")";
        
        if ($minCapacity) {
            $sql .= " AND r.capacity >= :min_capacity";
            $params['min_capacity'] = $minCapacity;
        }
        
        $sql .= " ORDER BY r.capacity ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get available teachers for a specific time slot
     */
    public function getAvailableTeachers($dayOfWeek, $startTime, $endTime, $semester, $academicYear, $excludeScheduleId = null, $departmentId = null) {
        $sql = "SELECT u.* FROM users u
                WHERE u.role = 'teacher' 
                AND u.is_active = 1
                AND u.id NOT IN (
                    SELECT cs.teacher_id 
                    FROM class_schedules cs
                    WHERE cs.day_of_week = :day_of_week 
                    AND cs.semester = :semester 
                    AND cs.academic_year = :academic_year 
                    AND cs.is_active = 1
                    AND (cs.start_time < :end_time AND cs.end_time > :start_time)";
        
        $params = [
            'day_of_week' => $dayOfWeek,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        if ($excludeScheduleId) {
            $sql .= " AND cs.id != :exclude_id";
            $params['exclude_id'] = $excludeScheduleId;
        }
        
        $sql .= ")";
        
        if ($departmentId) {
            $sql .= " AND u.id IN (
                SELECT d.head_of_department 
                FROM departments d 
                WHERE d.id = :department_id AND d.is_active = 1
            )";
            $params['department_id'] = $departmentId;
        }
        
        $sql .= " ORDER BY u.last_name, u.first_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get teacher's current schedule
     */
    public function getTeacherSchedule($teacherId, $semester, $academicYear) {
        $sql = "SELECT cs.*, c.course_code, c.course_name, d.name as department_name,
                       r.room_number, r.building
                FROM class_schedules cs
                JOIN courses c ON cs.course_id = c.id
                JOIN departments d ON c.department_id = d.id
                JOIN rooms r ON cs.room_id = r.id
                WHERE cs.teacher_id = :teacher_id 
                AND cs.semester = :semester 
                AND cs.academic_year = :academic_year 
                AND cs.is_active = 1
                ORDER BY cs.day_of_week, cs.start_time";
        
        return $this->db->fetchAll($sql, [
            'teacher_id' => $teacherId,
            'semester' => $semester,
            'academic_year' => $academicYear
        ]);
    }
    
    /**
     * Get room's current schedule
     */
    public function getRoomSchedule($roomId, $semester, $academicYear) {
        $sql = "SELECT cs.*, c.course_code, c.course_name,
                       CONCAT(u.first_name, ' ', u.last_name) as teacher_name
                FROM class_schedules cs
                JOIN courses c ON cs.course_id = c.id
                JOIN users u ON cs.teacher_id = u.id
                WHERE cs.room_id = :room_id 
                AND cs.semester = :semester 
                AND cs.academic_year = :academic_year 
                AND cs.is_active = 1
                ORDER BY cs.day_of_week, cs.start_time";
        
        return $this->db->fetchAll($sql, [
            'room_id' => $roomId,
            'semester' => $semester,
            'academic_year' => $academicYear
        ]);
    }
    
    /**
     * Create class schedule with conflict checking
     */
    public function createSchedule($courseId, $teacherId, $roomId, $dayOfWeek, $startTime, $endTime, $semester, $yearLevel, $academicYear) {
        try {
            // Check for conflicts first
            $conflicts = $this->checkConflicts($courseId, $teacherId, $roomId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear);
            
            if (!empty($conflicts)) {
                return [
                    'success' => false,
                    'conflicts' => $conflicts,
                    'message' => 'Scheduling conflicts detected'
                ];
            }
            
            // Insert the schedule
            $sql = "INSERT INTO class_schedules 
                    (course_id, teacher_id, room_id, day_of_week, start_time, end_time, semester, year_level, academic_year) 
                    VALUES (:course_id, :teacher_id, :room_id, :day_of_week, :start_time, :end_time, :semester, :year_level, :academic_year)";
            
            $params = [
                'course_id' => $courseId,
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'semester' => $semester,
                'year_level' => $yearLevel,
                'academic_year' => $academicYear
            ];
            
            $scheduleId = $this->db->insert($sql, $params);
            
            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'message' => 'Class scheduled successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create schedule: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update class schedule with conflict checking
     */
    public function updateSchedule($scheduleId, $courseId, $teacherId, $roomId, $dayOfWeek, $startTime, $endTime, $semester, $yearLevel, $academicYear) {
        try {
            // Check for conflicts (excluding current schedule)
            $conflicts = $this->checkConflicts($courseId, $teacherId, $roomId, $dayOfWeek, $startTime, $endTime, $semester, $academicYear, $scheduleId);
            
            if (!empty($conflicts)) {
                return [
                    'success' => false,
                    'conflicts' => $conflicts,
                    'message' => 'Scheduling conflicts detected'
                ];
            }
            
            // Update the schedule
            $sql = "UPDATE class_schedules 
                    SET course_id = :course_id, teacher_id = :teacher_id, room_id = :room_id, 
                        day_of_week = :day_of_week, start_time = :start_time, end_time = :end_time, 
                        semester = :semester, year_level = :year_level, academic_year = :academic_year
                    WHERE id = :id";
            
            $params = [
                'course_id' => $courseId,
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'semester' => $semester,
                'year_level' => $yearLevel,
                'academic_year' => $academicYear,
                'id' => $scheduleId
            ];
            
            $result = $this->db->update($sql, $params);
            
            return [
                'success' => $result > 0,
                'message' => $result > 0 ? 'Schedule updated successfully' : 'No changes made'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update schedule: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Format conflicts for display
     */
    public function formatConflicts($conflicts) {
        $formatted = [];
        
        foreach ($conflicts as $type => $conflictList) {
            $formatted[$type] = [];
            
            foreach ($conflictList as $conflict) {
                $message = '';
                
                switch ($type) {
                    case 'teacher':
                        $message = "Teacher conflict with {$conflict['course_code']} ({$conflict['course_name']}) - {$conflict['day_of_week']} {$conflict['start_time']}-{$conflict['end_time']}";
                        break;
                    case 'room':
                        $message = "Room conflict: {$conflict['room_number']} in {$conflict['building']} is occupied by {$conflict['course_code']} - {$conflict['day_of_week']} {$conflict['start_time']}-{$conflict['end_time']}";
                        break;
                    case 'course':
                        $message = "Course conflict: {$conflict['course_code']} is already scheduled at {$conflict['day_of_week']} {$conflict['start_time']}-{$conflict['end_time']} in {$conflict['room_number']}";
                        break;
                }
                
                $formatted[$type][] = [
                    'message' => $message,
                    'details' => $conflict
                ];
            }
        }
        
        return $formatted;
    }
}
?>
