<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhCertificate, PhGraduationCap, PhPlus, PhPlayCircle } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { usePermissions } from '@/composables/usePermissions'
import { trainingApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'
import type { TrainingComplianceRow, TrainingCourse, TrainingEnrollment } from '@/types'

const auth = useAuthStore()
const { can } = usePermissions()

const courses = ref<TrainingCourse[]>([])
const enrollments = ref<TrainingEnrollment[]>([])
const complianceRows = ref<TrainingComplianceRow[]>([])
const selectedCourseId = ref<number | null>(null)
const quizAnswers = ref<number[]>([])
const activeEnrollmentId = ref<number | null>(null)
const courseFilter = ref<'all' | 'enrolled' | 'required' | 'completed'>('all')
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const canAssign = computed(() => can('training.assign'))
const canCreate = computed(() => can('training.create'))
const selectedCourse = computed(() => courses.value.find((c) => c.id === selectedCourseId.value) ?? null)
const myEnrollments = computed(() =>
  enrollments.value.filter((e) => e.user_id === auth.user?.id),
)

const enrolledCount = computed(() => myEnrollments.value.length)

const inProgressCount = computed(
  () => myEnrollments.value.filter((e) => ['assigned', 'in_progress'].includes(e.status)).length,
)

const certificatesCount = computed(
  () => myEnrollments.value.filter((e) => e.certificate).length,
)

const filteredCourses = computed(() => {
  return courses.value.filter((course) => {
    const enrollment = enrollmentForCourse(course.id)
    if (courseFilter.value === 'enrolled') return Boolean(enrollment)
    if (courseFilter.value === 'required') return course.is_required
    if (courseFilter.value === 'completed') return enrollment?.status === 'completed'
    return true
  })
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const tasks: Promise<void>[] = [
      trainingApi.listCourses().then((rows) => {
        courses.value = rows
      }),
      trainingApi.listEnrollments().then((rows) => {
        enrollments.value = rows
      }),
    ]
    if (canAssign.value) {
      tasks.push(
        trainingApi.complianceReport().then((report) => {
          complianceRows.value = report.rows
        }),
      )
    }
    await Promise.all(tasks)
  } catch (err) {
    error.value = formatApiError(err, 'Training is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function enrollmentForCourse(courseId: number) {
  return myEnrollments.value.find((e) => e.training_course_id === courseId)
}

async function selfEnroll(course: TrainingCourse) {
  if (!auth.user?.id) return
  isSaving.value = true
  error.value = null
  try {
    const enrollment = await trainingApi.assignCourse({
      training_course_id: course.id,
      user_id: auth.user.id,
    })
    enrollments.value = [...enrollments.value.filter((e) => e.id !== enrollment.id), enrollment]
    selectedCourseId.value = course.id
    activeEnrollmentId.value = enrollment.id
    quizAnswers.value = (course.quiz_questions ?? []).map(() => 0)
    await trainingApi.startEnrollment(enrollment.id)
  } catch (err) {
    error.value = formatApiError(err, 'Could not enroll in course.')
  } finally {
    isSaving.value = false
  }
}

function openCourse(course: TrainingCourse) {
  selectedCourseId.value = course.id
  const enrollment = enrollmentForCourse(course.id)
  activeEnrollmentId.value = enrollment?.id ?? null
  quizAnswers.value = (course.quiz_questions ?? []).map(() => 0)
}

async function submitQuiz() {
  if (!activeEnrollmentId.value) return
  isSaving.value = true
  error.value = null
  success.value = null
  try {
    const result = await trainingApi.submitQuiz(activeEnrollmentId.value, quizAnswers.value)
    enrollments.value = enrollments.value.map((e) =>
      e.id === result.enrollment.id ? result.enrollment : e,
    )
    success.value = result.passed
      ? `Passed with ${result.score}%. Certificate ${result.certificate?.certificate_number ?? 'issued'}.`
      : `Score ${result.score}%. Passing score is ${result.passing_score}%.`
    if (canAssign.value) {
      const report = await trainingApi.complianceReport()
      complianceRows.value = report.rows
    }
  } catch (err) {
    error.value = formatApiError(err, 'Could not submit quiz.')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Training & CLE" subtitle="Courses, quizzes, credit tracking, and compliance.">
      <template #actions>
        <span v-if="canCreate" class="text-sm text-muted-foreground">
          Use admin tools to upload internal courses.
        </span>
      </template>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Available courses</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : courses.length }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Enrolled</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : enrolledCount }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">In progress</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : inProgressCount }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Certificates</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : certificatesCount }}
        </p>
      </div>
    </div>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Course filters</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button
          v-for="filter in [
            { key: 'all', label: 'All courses' },
            { key: 'enrolled', label: 'My enrollments' },
            { key: 'required', label: 'Required' },
            { key: 'completed', label: 'Completed' },
          ]"
          :key="filter.key"
          type="button"
          class="bw-btn bw-btn-sm"
          :class="courseFilter === filter.key ? 'bw-btn-action' : 'bw-btn-outline'"
          @click="courseFilter = filter.key as typeof courseFilter"
        >
          {{ filter.label }}
        </button>
      </div>
    </div>

    <p v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
      {{ error }}
    </p>
    <p v-if="success" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      {{ success }}
    </p>

    <div v-if="isLoading" class="grid gap-4 lg:grid-cols-2">
      <Skeleton class="h-64 rounded-xl" />
      <Skeleton class="h-64 rounded-xl" />
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
      <div class="space-y-4">
        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div class="flex items-center gap-2">
              <PhGraduationCap class="h-5 w-5 text-primary-700" />
              <div>
                <h2 class="font-semibold text-foreground">Courses</h2>
                <p class="text-sm text-muted-foreground">
                  {{ filteredCourses.length }} course{{ filteredCourses.length === 1 ? '' : 's' }}
                </p>
              </div>
            </div>
          </div>

          <EmptyState
            v-if="!filteredCourses.length"
            class="py-10"
            title="No courses"
            description="Courses will appear when published or adjust your filters."
          />

          <div v-else class="divide-y divide-border">
            <button
              v-for="course in filteredCourses"
              :key="course.id"
              type="button"
              class="flex w-full items-start justify-between gap-3 px-6 py-4 text-left transition-colors hover:bg-muted/40"
              :class="{ 'bg-surface-muted': selectedCourseId === course.id }"
              @click="openCourse(course)"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium text-foreground">{{ course.title }}</p>
                <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ course.description }}</p>
                <p class="mt-2 text-xs text-muted-foreground">
                  {{ course.cle_credits }} CLE credits · {{ course.quiz_question_count ?? 0 }} quiz questions
                </p>
              </div>
              <div class="flex shrink-0 flex-col items-end gap-2">
                <StatusBadge v-if="course.is_required" status="urgent" />
                <StatusBadge
                  :status="enrollmentForCourse(course.id)?.status ?? 'not_started'"
                />
              </div>
            </button>
          </div>
        </section>

        <div v-if="canAssign && complianceRows.length" class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <h2 class="font-semibold text-foreground">CLE compliance report</h2>
          </div>
          <div class="overflow-x-auto p-4 pt-0">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th class="px-3 py-2">Staff</th>
                  <th class="px-3 py-2">Required</th>
                  <th class="px-3 py-2">Compliance</th>
                  <th class="px-3 py-2">CLE earned</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in complianceRows"
                  :key="row.user_id"
                  class="border-b border-border/60"
                >
                  <td class="px-3 py-2 text-foreground">{{ row.name }}</td>
                  <td class="px-3 py-2 tabular-nums">
                    {{ row.required_courses_completed }}/{{ row.required_courses_total }}
                  </td>
                  <td class="px-3 py-2 tabular-nums">{{ row.compliance_percent }}%</td>
                  <td class="px-3 py-2 tabular-nums">{{ row.cle_credits_earned }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="bw-card overflow-hidden">
        <template v-if="selectedCourse">
          <div class="bw-card-header">
            <div>
              <h2 class="font-semibold text-foreground">{{ selectedCourse.title }}</h2>
              <p class="text-sm text-muted-foreground">
                {{ selectedCourse.cle_credits }} CLE credits
                <span v-if="selectedCourse.is_required"> · Required</span>
              </p>
            </div>
            <StatusBadge
              :status="enrollmentForCourse(selectedCourse.id)?.status ?? 'not_started'"
            />
          </div>

          <div class="space-y-6 p-6">
            <p class="text-sm text-muted-foreground">{{ selectedCourse.content || selectedCourse.description }}</p>

            <div class="flex flex-wrap gap-3 text-sm">
              <a
                v-if="selectedCourse.video_url"
                :href="selectedCourse.video_url"
                target="_blank"
                rel="noopener noreferrer"
                class="bw-btn bw-btn-outline bw-btn-sm inline-flex items-center gap-2"
              >
                <PhPlayCircle class="h-4 w-4" />
                Watch video
              </a>
              <a
                v-if="selectedCourse.materials_url"
                :href="selectedCourse.materials_url"
                target="_blank"
                rel="noopener noreferrer"
                class="bw-btn bw-btn-outline bw-btn-sm"
              >
                Download materials
              </a>
            </div>

            <div v-if="!activeEnrollmentId">
              <button type="button" class="bw-btn bw-btn-action" :disabled="isSaving" @click="selfEnroll(selectedCourse)">
                <PhPlus class="h-4 w-4" />
                Enroll & start
              </button>
            </div>

            <div v-else-if="selectedCourse.quiz_questions?.length" class="space-y-4">
              <h3 class="font-medium text-foreground">Quiz</h3>
              <div
                v-for="(question, qIndex) in selectedCourse.quiz_questions"
                :key="qIndex"
                class="rounded-lg border border-border bg-surface-muted p-4"
              >
                <p class="font-medium text-foreground">{{ question.question }}</p>
                <div class="mt-3 space-y-2">
                  <label
                    v-for="(option, oIndex) in question.options"
                    :key="oIndex"
                    class="flex items-center gap-2 text-sm text-foreground"
                  >
                    <input
                      v-model="quizAnswers[qIndex]"
                      type="radio"
                      :name="`quiz-${selectedCourse.id}-${qIndex}`"
                      :value="oIndex"
                    />
                    {{ option }}
                  </label>
                </div>
              </div>
              <button type="button" class="bw-btn bw-btn-action" :disabled="isSaving" @click="submitQuiz">
                Submit quiz
              </button>
            </div>

            <div
              v-if="enrollmentForCourse(selectedCourse.id)?.certificate"
              class="rounded-lg border border-border bg-surface-muted p-4"
            >
              <div class="flex items-center gap-2">
                <PhCertificate class="h-5 w-5 text-primary-700" />
                <p class="font-medium text-foreground">Certificate issued</p>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ enrollmentForCourse(selectedCourse.id)?.certificate?.certificate_number }}
              </p>
            </div>
          </div>
        </template>
        <EmptyState
          v-else
          class="py-16"
          title="Select a course"
          description="Choose a course to review materials and take the quiz."
        />
      </div>
    </div>
  </div>
</template>
