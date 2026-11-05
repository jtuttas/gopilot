import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query'
import { api } from './functions'
import type {
  Overview,
  PathMetaItem,
  SaveCourseConfigParams,
  SaveReflectionParams,
  WeekString,
} from './types'

export const queryKeys = {
  overview: ['overview'] as const,
  paths: (courseid: number) => ['paths', courseid] as const,
  week: (week: WeekString) => ['week', week] as const,
  biography: ['biography'] as const,
  lerndoku: (courseid: number) => ['lerndoku', courseid] as const,
  learner: (courseid: number, userid: number) => ['learner', courseid, userid] as const,
  courseconfig: (courseid: number) => ['courseconfig', courseid] as const,
  pushconfig: ['pushconfig'] as const,
}

/** Learner data that changes when plans, reflections or completions change. */
function invalidateLearnerData(qc: QueryClient) {
  return Promise.all([
    qc.invalidateQueries({ queryKey: queryKeys.overview }),
    qc.invalidateQueries({ queryKey: ['week'] }),
    qc.invalidateQueries({ queryKey: ['paths'] }),
    qc.invalidateQueries({ queryKey: queryKeys.biography }),
  ])
}

export function useOverview() {
  return useQuery({ queryKey: queryKeys.overview, queryFn: api.getOverview })
}

export function usePaths(courseid: number | undefined) {
  return useQuery({
    queryKey: queryKeys.paths(courseid ?? 0),
    queryFn: () => api.getPaths(courseid!),
    enabled: !!courseid,
  })
}

export function useWeek(week: WeekString | undefined) {
  return useQuery({
    queryKey: queryKeys.week(week ?? ''),
    queryFn: () => api.getWeek(week!),
    enabled: !!week,
  })
}

export function useSavePlan() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (v: { week: WeekString; goal: string; cmids: number[] }) =>
      api.savePlan(v.week, v.goal, v.cmids),
    onSuccess: (week) => {
      qc.setQueryData(queryKeys.week(week.week), week)
      return invalidateLearnerData(qc)
    },
  })
}

export function useSaveReflection() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (p: SaveReflectionParams) => api.saveReflection(p),
    onSuccess: (week) => {
      qc.setQueryData(queryKeys.week(week.week), week)
      return invalidateLearnerData(qc)
    },
  })
}

export function useStepCompletion() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (v: { cmid: number; completed: boolean }) =>
      api.setStepCompletion(v.cmid, v.completed),
    onSettled: () => invalidateLearnerData(qc),
  })
}

export function useBiography(perpage = 10) {
  return useInfiniteQuery({
    queryKey: queryKeys.biography,
    queryFn: ({ pageParam }) => api.getBiography(pageParam, perpage),
    initialPageParam: 0,
    getNextPageParam: (last, pages) => {
      const loaded = pages.reduce((n, p) => n + p.weeks.length, 0)
      return loaded < last.total && last.weeks.length > 0 ? pages.length : undefined
    },
  })
}

export function useLerndoku(courseid: number | undefined) {
  return useQuery({
    queryKey: queryKeys.lerndoku(courseid ?? 0),
    queryFn: () => api.getLerndoku(courseid!),
    enabled: !!courseid,
  })
}

export function useLearner(courseid: number, userid: number) {
  return useQuery({
    queryKey: queryKeys.learner(courseid, userid),
    queryFn: () => api.getLearner(courseid, userid),
    enabled: courseid > 0 && userid > 0,
  })
}

export function useAddFeedback() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (v: { courseid: number; userid: number; week: WeekString; message: string }) =>
      api.addFeedback(v.courseid, v.userid, v.week, v.message),
    onSuccess: (_entry, v) =>
      Promise.all([
        qc.invalidateQueries({ queryKey: queryKeys.week(v.week) }),
        qc.invalidateQueries({ queryKey: queryKeys.learner(v.courseid, v.userid) }),
        qc.invalidateQueries({ queryKey: queryKeys.biography }),
      ]),
  })
}

/** The learner has seen coaching feedback: removes it from the start page right away. */
export function useMarkFeedbackRead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ids: number[]) => api.markFeedbackRead(ids),
    onMutate: (ids) => {
      qc.setQueryData<Overview>(queryKeys.overview, (o) =>
        o?.newfeedback ? { ...o, newfeedback: o.newfeedback.filter((f) => !ids.includes(f.id)) } : o,
      )
    },
    onSettled: () =>
      Promise.all([
        qc.invalidateQueries({ queryKey: queryKeys.overview }),
        qc.invalidateQueries({ queryKey: ['week'] }),
        qc.invalidateQueries({ queryKey: queryKeys.biography }),
      ]),
  })
}

export function useCourseConfig(courseid: number | undefined) {
  return useQuery({
    queryKey: queryKeys.courseconfig(courseid ?? 0),
    queryFn: () => api.getCourseConfig(courseid!),
    enabled: !!courseid,
  })
}

export function useSaveCourseConfig() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (p: SaveCourseConfigParams) => api.saveCourseConfig(p),
    onSuccess: (config) => {
      qc.setQueryData(queryKeys.courseconfig(config.courseid), config)
      return Promise.all([
        qc.invalidateQueries({ queryKey: queryKeys.overview }),
        qc.invalidateQueries({ queryKey: queryKeys.lerndoku(config.courseid) }),
      ])
    },
  })
}

export function useSavePathMeta() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (v: { courseid: number; items: PathMetaItem[] }) =>
      api.savePathMeta(v.courseid, v.items),
    onSuccess: (_r, v) =>
      Promise.all([
        qc.invalidateQueries({ queryKey: queryKeys.courseconfig(v.courseid) }),
        qc.invalidateQueries({ queryKey: queryKeys.paths(v.courseid) }),
      ]),
  })
}
