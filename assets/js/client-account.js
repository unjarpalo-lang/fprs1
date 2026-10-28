// Uses a separate, memory-only Auth instance so reauthentication does not
// switch the application's existing Firebase session or PHP account.
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
import { initializeAuth, inMemoryPersistence, signInWithEmailAndPassword, reauthenticateWithCredential, EmailAuthProvider, updatePassword, signOut } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';
import { getFirebaseApp } from './firebase-init.js';

export function attachPasswordChange() {
  const form = document.getElementById('firebase-password-form');
  const button = document.getElementById('firebase-password-submit');
  const status = document.getElementById('firebase-password-status');
  const auth = initializeAuth(initializeApp(getFirebaseApp().options, 'fprs-account-security'), { persistence: inMemoryPersistence });
  button.disabled = false;
  button.type = 'submit';
  status.textContent = 'Ready. Your current Firebase password is required.';
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const fields = new FormData(form), password = fields.get('new_password');
    if (password !== fields.get('confirm_password')) { status.textContent = 'New passwords do not match.'; return; }
    button.disabled = true; button.dataset.busy = '1'; status.textContent = 'Verifying your account…';
    try {
      const result = await signInWithEmailAndPassword(auth, form.dataset.email, fields.get('current_password'));
      if (result.user.uid !== form.dataset.uid) throw new Error('account-mismatch');
      await reauthenticateWithCredential(result.user, EmailAuthProvider.credential(form.dataset.email, fields.get('current_password')));
      await updatePassword(result.user, password);
      status.textContent = 'Firebase password changed.';
    } catch (error) {
      status.textContent = error.code === 'auth/network-request-failed' ? 'Connection failed. Please try again.'
        : error.code === 'auth/weak-password' || error.code === 'auth/password-does-not-meet-requirements' ? 'Choose a stronger password that meets your account policy.'
        : 'Password was not changed. Check your current Firebase password and try again. If you use another sign-in provider, manage your password with that provider.';
    } finally {
      form.reset();
      try { await signOut(auth); } catch (_) { /* Memory-only credentials are not persisted. */ }
      button.disabled = false; delete button.dataset.busy;
    }
  });
}
