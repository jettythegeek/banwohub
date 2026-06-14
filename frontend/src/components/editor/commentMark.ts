import { Mark, mergeAttributes } from '@tiptap/core'

export type EditorComment = {
  id: string
  text: string
  author: string
}

export const CommentMark = Mark.create({
  name: 'comment',

  addAttributes() {
    return {
      id: { default: null },
      text: { default: '' },
      author: { default: '' },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'mark[data-comment-id]',
        getAttrs: (node) => {
          if (typeof node === 'string') return false
          const el = node as HTMLElement
          return {
            id: el.getAttribute('data-comment-id'),
            text: el.getAttribute('data-comment-text') ?? '',
            author: el.getAttribute('data-comment-author') ?? '',
          }
        },
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'mark',
      mergeAttributes(HTMLAttributes, {
        'data-comment-id': HTMLAttributes.id,
        'data-comment-text': HTMLAttributes.text,
        'data-comment-author': HTMLAttributes.author,
        class: 'bw-editor-comment',
      }),
      0,
    ]
  },
})

export function extractCommentsFromHtml(html: string): EditorComment[] {
  if (!html || typeof DOMParser === 'undefined') return []

  const doc = new DOMParser().parseFromString(html, 'text/html')
  const marks = doc.querySelectorAll('mark[data-comment-id]')
  const seen = new Set<string>()
  const comments: EditorComment[] = []

  marks.forEach((mark) => {
    const id = mark.getAttribute('data-comment-id')
    if (!id || seen.has(id)) return
    seen.add(id)
    comments.push({
      id,
      text: mark.getAttribute('data-comment-text') ?? mark.textContent ?? '',
      author: mark.getAttribute('data-comment-author') ?? 'Staff',
    })
  })

  return comments
}
