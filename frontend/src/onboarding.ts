const DONE_KEY = 'gitlife_onboarding_v1'

export function isOnboardingDone(): boolean {
  return localStorage.getItem(DONE_KEY) === '1'
}

export function markOnboardingDone(): void {
  localStorage.setItem(DONE_KEY, '1')
}

/** Existing users who already accepted privacy skip the tour once. */
export function migrateOnboardingIfNeeded(privacyAccepted: boolean): void {
  if (privacyAccepted && !isOnboardingDone()) {
    markOnboardingDone()
  }
}
