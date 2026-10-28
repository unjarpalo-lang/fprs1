// Standalone Firebase Authentication module — intentionally isolated from
// every other script in the app, same convention as firebase-chat.js.
// Handles sign-up/sign-in for the two client-facing roles ("Family" ->
// stored as role "client", and "Funeral Director" -> role "director"),
// then hands the resulting ID token to api/firebase_auth_sync.php, which
// establishes the SAME PHP session (`$_SESSION['user_id']`) the rest of
// this app already relies on. Firebase only replaces *how the password is
// checked* — every existing page keeps working unchanged.
//
// Usage:
//   <script type="module">
//     import { registerUser, loginUser, logoutUser } from "/fprs/assets/js/firebase-auth.js";
//
//     registerForm.addEventListener("submit", async (e) => {
//       e.preventDefault();
//       try {
//         const { redirect } = await registerUser({
//           email: emailInput.value,
//           password: passwordInput.value,
//           role: "client",           // or "director"
//           fullName: fullNameInput.value,
//           phone: phoneInput.value,
//           funeralName: funeralNameInput.value, // directors only
//         });
//         window.location.href = redirect;
//       } catch (err) {
//         showError(err.message);
//       }
//     });
//   </script>
//
// Credentials live in firebase-init.js — edit them there, not here.

import {
  getAuth,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signOut,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
import { getFirebaseApp } from "./firebase-init.js";

const SYNC_ENDPOINT = "/fprs/api/firebase_auth_sync.php";

function getAuthInstance() {
  return getAuth(getFirebaseApp());
}

async function syncSession(idToken, mode, extra = {}) {
  const res = await fetch(SYNC_ENDPOINT, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin", // send/receive the PHP session cookie
    body: JSON.stringify({ idToken, mode, ...extra }),
  });
  const data = await res.json();
  if (!res.ok || !data.ok) {
    throw new Error(data.error || "Could not complete sign-in.");
  }
  return data; // { ok, role, redirect }
}

/**
 * Register a new Family or Funeral Director account.
 * @param {{email:string, password:string, role:"client"|"director", fullName:string, phone?:string, funeralName?:string}} input
 * @returns {Promise<{role:string, redirect:string}>}
 */
export async function registerUser({ email, password, role, fullName, phone = "", funeralName = "" }) {
  if (!email || !password || !fullName) {
    throw new Error("Email, password, and full name are required.");
  }
  const auth = getAuthInstance();
  const credential = await createUserWithEmailAndPassword(auth, email, password);
  const idToken = await credential.user.getIdToken();
  return syncSession(idToken, "register", { role, full_name: fullName, phone, funeral_name: funeralName });
}

/**
 * Log in an existing Family or Funeral Director account.
 * @param {{email:string, password:string}} input
 * @returns {Promise<{role:string, redirect:string}>}
 */
export async function loginUser({ email, password }) {
  if (!email || !password) {
    throw new Error("Email and password are required.");
  }
  const auth = getAuthInstance();
  const credential = await signInWithEmailAndPassword(auth, email, password);
  const idToken = await credential.user.getIdToken();
  return syncSession(idToken, "login");
}

/** Signs out of Firebase and clears the PHP session via the existing logout.php. */
export async function logoutUser() {
  const auth = getAuthInstance();
  await signOut(auth);
  window.location.href = "/fprs/logout.php";
}
