import type { Component } from 'vue'
import {
  PhCalendarBlank,
  PhCaretDown,
  PhCheckSquare,
  PhCircle,
  PhEnvelopeSimple,
  PhFileArrowUp,
  PhListBullets,
  PhMapPin,
  PhParagraph,
  PhPhone,
  PhRadioButton,
  PhSignature,
  PhTextAa,
  PhTextAlignLeft,
  PhUser,
} from '@phosphor-icons/vue'
import type { IntakeField } from '@/types'

export type FieldCatalogItem = {
  id: string
  label: string
  icon: Component
  create: () => IntakeField
}

let fieldCounter = 0

function nextFieldName(prefix: string) {
  fieldCounter += 1
  return `${prefix}_${fieldCounter}`
}

export function resetFieldCounter() {
  fieldCounter = 0
}

export function syncFieldCounterFromFields(fields: IntakeField[]) {
  const max = fields.reduce((highest, field) => {
    const match = field.name.match(/_(\d+)$/)
    if (!match) return highest
    return Math.max(highest, Number(match[1]))
  }, 0)
  fieldCounter = max
}

export const paletteFieldTypes: FieldCatalogItem[] = [
  {
    id: 'paragraph',
    label: 'Paragraph',
    icon: PhParagraph,
    create: () => ({
      name: nextFieldName('paragraph'),
      label: 'Instructions',
      type: 'long_text',
      required: false,
    }),
  },
  {
    id: 'text',
    label: 'Form field',
    icon: PhTextAa,
    create: () => ({
      name: nextFieldName('field'),
      label: 'New field',
      type: 'text',
      required: false,
    }),
  },
  {
    id: 'long_text',
    label: 'Text area',
    icon: PhTextAlignLeft,
    create: () => ({
      name: nextFieldName('notes'),
      label: 'Additional details',
      type: 'long_text',
      required: false,
    }),
  },
  {
    id: 'radio',
    label: 'Radio button',
    icon: PhRadioButton,
    create: () => ({
      name: nextFieldName('choice'),
      label: 'Choose one',
      type: 'radio',
      required: false,
      options: ['Option A', 'Option B'],
    }),
  },
  {
    id: 'checkbox',
    label: 'Checkbox',
    icon: PhCheckSquare,
    create: () => ({
      name: nextFieldName('options'),
      label: 'Select all that apply',
      type: 'checkbox',
      required: false,
      options: ['Option A', 'Option B'],
    }),
  },
  {
    id: 'date',
    label: 'Date picker',
    icon: PhCalendarBlank,
    create: () => ({
      name: nextFieldName('date'),
      label: 'Date',
      type: 'date',
      required: false,
    }),
  },
  {
    id: 'dropdown',
    label: 'Dropdown',
    icon: PhCaretDown,
    create: () => ({
      name: nextFieldName('select'),
      label: 'Select option',
      type: 'dropdown',
      required: false,
      options: ['Option A', 'Option B'],
    }),
  },
  {
    id: 'file',
    label: 'Attachment',
    icon: PhFileArrowUp,
    create: () => ({
      name: nextFieldName('attachment'),
      label: 'Upload file',
      type: 'file',
      required: false,
    }),
  },
  {
    id: 'signature',
    label: 'Signature',
    icon: PhSignature,
    create: () => ({
      name: nextFieldName('signature'),
      label: 'Signature',
      type: 'signature',
      required: false,
    }),
  },
  {
    id: 'conditional',
    label: 'Conditional',
    icon: PhListBullets,
    create: () => ({
      name: nextFieldName('conditional'),
      label: 'Conditional field',
      type: 'conditional',
      required: false,
      conditions: { field: '', equals: '' },
    }),
  },
]

export const suggestedFields: FieldCatalogItem[] = [
  {
    id: 'full_name',
    label: 'Full name',
    icon: PhUser,
    create: () => ({
      name: 'full_name',
      label: 'Full name',
      type: 'text',
      required: true,
    }),
  },
  {
    id: 'gender',
    label: 'Gender',
    icon: PhCircle,
    create: () => ({
      name: 'gender',
      label: 'Gender',
      type: 'radio',
      required: false,
      options: ['Male', 'Female', 'Prefer not to say'],
    }),
  },
  {
    id: 'date_of_birth',
    label: 'Date of birth',
    icon: PhCalendarBlank,
    create: () => ({
      name: 'date_of_birth',
      label: 'Date of birth',
      type: 'date',
      required: false,
    }),
  },
  {
    id: 'phone',
    label: 'Phone',
    icon: PhPhone,
    create: () => ({
      name: 'phone',
      label: 'Phone',
      type: 'phone',
      required: false,
    }),
  },
  {
    id: 'email',
    label: 'Email',
    icon: PhEnvelopeSimple,
    create: () => ({
      name: 'email',
      label: 'Email',
      type: 'email',
      required: true,
    }),
  },
  {
    id: 'city',
    label: 'City',
    icon: PhMapPin,
    create: () => ({
      name: 'city',
      label: 'City',
      type: 'text',
      required: false,
    }),
  },
]

export function cloneCatalogField(item: FieldCatalogItem): IntakeField {
  return { ...item.create() }
}

export function fieldTypeLabel(type: string) {
  const match = paletteFieldTypes.find((item) => item.id === type)
  if (match) return match.label
  return type.replace(/_/g, ' ')
}

export function fieldNeedsOptions(type: string) {
  return ['dropdown', 'checkbox', 'radio'].includes(type)
}
