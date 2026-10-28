// Single shared Firebase app instance for the whole front end. Every other
// firebase-*.js module (firebase-auth.js, firebase-inventory.js,
// firebase-chat.js) imports getFirebaseApp() from here instead of each
// keeping its own copy of firebaseConfig — one place to update credentials.
import { initializeApp, getApps, getApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";

const firebaseConfig = {
  apiKey: "AIzaSyBiFXMNgcISwXCo3yRCOCv4caLsL6g5cpI",
  authDomain: "fprs-a8789.firebaseapp.com",
  projectId: "fprs-a8789",
  storageBucket: "fprs-a8789.firebasestorage.app",
  messagingSenderId: "300989129255",
  appId: "1:300989129255:web:63bc53c0b647b0c0e2e402",
  measurementId: "G-PZ5G8SG1NS",
  databaseURL: "https://fprs-a8789-default-rtdb.firebaseio.com",
};

/** Returns the shared Firebase app, initializing it on first call. */
export function getFirebaseApp() {
  return getApps().length ? getApp() : initializeApp(firebaseConfig);
}
