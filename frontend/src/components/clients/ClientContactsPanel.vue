<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { PhEnvelopeSimple, PhPhone, PhPlus, PhUser } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { clientContactsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CLIENT_CONTACT_TYPES, clientContactTypeLabel } from '@/lib/enums'
import type { ClientContact, ClientContactType } from '@/types'

const props = defineProps<{
  clientId: number
  embedded?: boolean
}>()

const contacts = ref<ClientContact[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showModal = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  type: 'primary' as ClientContactType,
  name: '',
  email: '',
  phone: '',
  title: '',
})

function resetForm() {
  form.value = { type: 'primary', name: '', email: '', phone: '', title: '' }
  editingId.value = null
}

function openCreate() {
  resetForm()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

async function loadContacts() {
  isLoading.value = true
  error.value = null
  try {
    contacts.value = await clientContactsApi.list(props.clientId)
  } catch (err) {
    error.value = formatApiError(err, 'Contacts are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function startEdit(contact: ClientContact) {
  editingId.value = contact.id
  form.value = {
    type: contact.type,
    name: contact.name,
    email: contact.email ?? '',
    phone: contact.phone ?? '',
    title: contact.title ?? '',
  }
  showModal.value = true
}

async function saveContact() {
  if (!form.value.name.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const payload = {
      type: form.value.type,
      name: form.value.name.trim(),
      email: form.value.email.trim() || null,
      phone: form.value.phone.trim() || null,
      title: form.value.title.trim() || null,
    }
    if (editingId.value) {
      await clientContactsApi.update(editingId.value, payload)
    } else {
      await clientContactsApi.create({ client_id: props.clientId, ...payload })
    }
    closeModal()
    await loadContacts()
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isSaving.value = false
  }
}

async function removeContact(id: number) {
  if (!confirm('Remove this contact?')) return
  error.value = null
  try {
    await clientContactsApi.remove(id)
    await loadContacts()
  } catch (err) {
    error.value = formatApiError(err)
  }
}

onMounted(loadContacts)
</script>

<template>
  <section :class="embedded ? '' : 'bw-card overflow-hidden'">
    <div :class="embedded ? 'mb-4 flex items-start justify-between gap-4' : 'bw-card-header'">
      <div>
        <h2 class="font-semibold text-foreground">Contacts</h2>
        <p class="text-sm text-muted-foreground">
          Primary, billing, opposing party, and witness contacts.
        </p>
      </div>
      <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openCreate">
        <PhPlus class="h-4 w-4" weight="bold" />
        Add contact
      </button>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-5" />
    <p v-else-if="error" class="p-5 text-sm text-destructive" role="alert">{{ error }}</p>

    <div
      v-if="!isLoading && contacts.length"
      :class="embedded ? 'bw-card divide-y divide-border overflow-hidden' : 'divide-y divide-border'"
    >
      <article
        v-for="contact in contacts"
        :key="contact.id"
        class="flex flex-wrap items-start justify-between gap-4 px-5 py-4"
      >
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <PhUser class="h-4 w-4 text-muted-foreground" />
            <span class="font-medium text-foreground">{{ contact.name }}</span>
            <span class="bw-badge bw-badge-neutral">{{ clientContactTypeLabel(contact.type) }}</span>
          </div>
          <p v-if="contact.title" class="mt-1 text-sm text-muted-foreground">{{ contact.title }}</p>
          <div class="mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground">
            <span v-if="contact.email" class="inline-flex items-center gap-1">
              <PhEnvelopeSimple class="h-3.5 w-3.5" />
              {{ contact.email }}
            </span>
            <span v-if="contact.phone" class="inline-flex items-center gap-1">
              <PhPhone class="h-3.5 w-3.5" />
              {{ contact.phone }}
            </span>
          </div>
        </div>
        <div class="flex gap-2">
          <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="startEdit(contact)">
            Edit
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm text-destructive"
            @click="removeContact(contact.id)"
          >
            Remove
          </button>
        </div>
      </article>
    </div>
    <EmptyState
      v-else-if="!isLoading"
      title="No contacts yet"
      description="Add billing, opposing party, or witness contacts for this client."
      :class="embedded ? 'bw-card py-10' : 'py-10'"
    />

    <BwModal
      :open="showModal"
      :title="editingId ? 'Edit contact' : 'Add contact'"
      @close="closeModal"
    >
      <form class="space-y-4" @submit.prevent="saveContact">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label">Type</label>
            <select v-model="form.type" class="bw-select">
              <option v-for="t in CLIENT_CONTACT_TYPES" :key="t" :value="t">
                {{ clientContactTypeLabel(t) }}
              </option>
            </select>
          </div>
          <div>
            <label class="bw-label">Name</label>
            <input v-model="form.name" required class="bw-input" />
          </div>
          <div>
            <label class="bw-label">Title</label>
            <input v-model="form.title" class="bw-input" placeholder="e.g. General counsel" />
          </div>
          <div>
            <label class="bw-label">Email</label>
            <input v-model="form.email" type="email" class="bw-input" />
          </div>
          <div>
            <label class="bw-label">Phone</label>
            <input v-model="form.phone" class="bw-input" />
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-border pt-4">
          <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">Cancel</button>
          <button type="submit" class="bw-btn bw-btn-action" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId ? 'Update contact' : 'Save contact' }}
          </button>
        </div>
      </form>
    </BwModal>
  </section>
</template>
