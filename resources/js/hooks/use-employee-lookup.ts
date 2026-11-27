import { useEffect, useState } from 'react';
import employeeLookup from '@/actions/App/Http/Controllers/Public/EmployeeLookupController';
import type { Employee } from '@/lib/employee-search';

export function useEmployeeLookup(departmentId: number | null) {
    const [cache, setCache] = useState<Partial<Record<number, Employee[]>>>({});
    const [failedDepartment, setFailedDepartment] = useState<number | null>(
        null,
    );
    const [attempt, setAttempt] = useState(0);
    const employees = departmentId === null ? undefined : cache[departmentId];

    useEffect(() => {
        if (departmentId === null || employees !== undefined) {
            return;
        }

        const controller = new AbortController();
        void fetch(employeeLookup.url(departmentId), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Employee lookup failed');
                }

                const data = (await response.json()) as Employee[];
                if (!controller.signal.aborted) {
                    setCache((current) => ({
                        ...current,
                        [departmentId]: data,
                    }));
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setFailedDepartment(departmentId);
                }
            });

        return () => controller.abort();
    }, [attempt, departmentId, employees]);

    const unavailable = departmentId !== null && employees === undefined;

    return {
        employees,
        unavailable,
        failed: unavailable && failedDepartment === departmentId,
        retry: () => {
            setFailedDepartment(null);
            setAttempt((current) => current + 1);
        },
    };
}
