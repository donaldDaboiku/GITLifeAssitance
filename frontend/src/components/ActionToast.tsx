type ActionToastProps = {
  message: string
  onUndo: () => void
}

export function ActionToast({ message, onUndo }: ActionToastProps) {
  return (
    <div className="action-toast" role="status" aria-live="polite">
      <span>{message}</span>
      <button type="button" className="toast-undo" onClick={onUndo}>
        Undo
      </button>
    </div>
  )
}
