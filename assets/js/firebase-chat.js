// Standalone Firebase chat module — intentionally isolated from every other
// script in the app. Import the functions you need; do not merge this into
// site.js or any inline <script> block.
//
// Usage:
//   <script type="module">
//     import { sendMessage, listenToThread } from "/fprs/assets/js/firebase-chat.js";
//     listenToThread("thread-123", (messages) => renderMessages(messages));
//     sendMessage("thread-123", currentUserId, "Hello");
//   </script>
//
// Credentials live in firebase-init.js — edit them there, not here.

import {
  getFirestore,
  collection,
  addDoc,
  query,
  orderBy,
  onSnapshot,
  serverTimestamp,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";
import { getFirebaseApp } from "./firebase-init.js";

function getDb() {
  return getFirestore(getFirebaseApp());
}

/**
 * Send a chat message into a thread (e.g. a client <-> director conversation).
 * @param {string} threadId
 * @param {string|number} senderId
 * @param {string} text
 */
export async function sendMessage(threadId, senderId, text) {
  if (!threadId || !senderId || !text || !text.trim()) {
    throw new Error("threadId, senderId, and non-empty text are required.");
  }
  const db = getDb();
  await addDoc(collection(db, "chat_threads", String(threadId), "messages"), {
    senderId,
    text: text.trim(),
    createdAt: serverTimestamp(),
  });
}

/**
 * Subscribe to a thread's messages in real time.
 * @param {string} threadId
 * @param {(messages: Array<{id: string, senderId: any, text: string, createdAt: any}>) => void} onMessages
 * @returns {() => void} unsubscribe function
 */
export function listenToThread(threadId, onMessages) {
  if (!threadId) throw new Error("threadId is required.");
  const db = getDb();
  const messagesQuery = query(
    collection(db, "chat_threads", String(threadId), "messages"),
    orderBy("createdAt", "asc")
  );
  return onSnapshot(messagesQuery, (snapshot) => {
    onMessages(snapshot.docs.map((doc) => ({ id: doc.id, ...doc.data() })));
  });
}
