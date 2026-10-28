// Standalone Firebase Realtime Database module for live inventory — same
// isolation convention as firebase-chat.js / firebase-auth.js. SQL
// (inventory_resources table) stays the source of truth for an item's
// identity; this module is only for the fast-changing, real-time fields
// (quantity/status) that a director updates and a client's UI should
// reflect instantly without polling.
//
// RTDB shape:
//   inventory/
//     {providerId}/                 <- funeral_parlors.id from your SQL DB
//       {itemId}/                   <- inventory_resources.id (same row, kept in sync)
//         name: "White Lily Wreath"
//         item_type: "flower"
//         quantity: 12
//         status: "available" | "insufficient" | "missing"
//         updated_at: <server timestamp>
//         updated_by: "<firebase uid of the director who last touched it>"
//   providers/
//     {providerId}/
//       director_uid: "<firebase uid>"   <- written server-side only, see
//                                            api/firebase_provider_link.php
//                                            and firebase.rules.json
//       name: "Salome Funeral Services"
//
// Usage (client-facing "is this still available" view):
//   import { listenToInventory } from "/fprs/assets/js/firebase-inventory.js";
//   const unsubscribe = listenToInventory(providerId, (items) => renderInventory(items));
//   // later: unsubscribe();
//
// Usage (director's own management screen):
//   import { upsertInventoryItem, deriveStatus } from "/fprs/assets/js/firebase-inventory.js";
//   await upsertInventoryItem(providerId, itemId, {
//     name: "White Lily Wreath", itemType: "flower", quantity: 3,
//     status: deriveStatus(3), // or pass an explicit override
//   });
//
// Credentials live in firebase-init.js — edit them there, not here.

import { getAuth } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
import {
  getDatabase,
  ref,
  onValue,
  update,
  remove,
  serverTimestamp,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-database.js";
import { getFirebaseApp } from "./firebase-init.js";

const VALID_STATUSES = ["available", "insufficient", "missing"];

function getDb() {
  return getDatabase(getFirebaseApp());
}

/** available above a threshold, insufficient if low but non-zero, missing at zero. */
export function deriveStatus(quantity, lowThreshold = 3) {
  const qty = Number(quantity) || 0;
  if (qty <= 0) return "missing";
  if (qty < lowThreshold) return "insufficient";
  return "available";
}

/**
 * Live-subscribe to every item under a provider's inventory.
 * @param {string|number} providerId
 * @param {(items: Array<{id:string, name:string, item_type:string, quantity:number, status:string}>) => void} onItems
 * @returns {() => void} unsubscribe
 */
export function listenToInventory(providerId, onItems) {
  if (!providerId) throw new Error("providerId is required.");
  const db = getDb();
  const inventoryRef = ref(db, `inventory/${providerId}`);
  return onValue(inventoryRef, (snapshot) => {
    const value = snapshot.val() || {};
    const items = Object.entries(value).map(([id, item]) => ({ id, ...item }));
    onItems(items);
  });
}

/**
 * Live-subscribe to a single item (e.g. a package's linked casket/flowers
 * shown on a client's recommendation card).
 * @returns {() => void} unsubscribe
 */
export function listenToInventoryItem(providerId, itemId, onItem) {
  if (!providerId || !itemId) throw new Error("providerId and itemId are required.");
  const db = getDb();
  const itemRef = ref(db, `inventory/${providerId}/${itemId}`);
  return onValue(itemRef, (snapshot) => onItem(snapshot.val()));
}

/**
 * Create or update one inventory item. Only succeeds if the signed-in
 * director's uid matches providers/{providerId}/director_uid — enforced by
 * firebase.rules.json, not just this client code.
 * @param {string|number} providerId
 * @param {string|number} itemId - use the SQL inventory_resources.id so the two stay linked
 * @param {{name:string, itemType:string, quantity:number, status?:string}} fields
 */
export async function upsertInventoryItem(providerId, itemId, { name, itemType, quantity, status }) {
  if (!providerId || !itemId) throw new Error("providerId and itemId are required.");
  const resolvedStatus = status ?? deriveStatus(quantity);
  if (!VALID_STATUSES.includes(resolvedStatus)) {
    throw new Error(`status must be one of: ${VALID_STATUSES.join(", ")}`);
  }
  const db = getDb();
  const auth = getAuth();
  const uid = auth.currentUser?.uid;
  if (!uid) throw new Error("You must be signed in to update inventory.");
  await update(ref(db, `inventory/${providerId}/${itemId}`), {
    name,
    item_type: itemType,
    quantity: Number(quantity) || 0,
    status: resolvedStatus,
    updated_at: serverTimestamp(),
    updated_by: uid,
  });
}

/** Remove an item entirely (e.g. after deleting it in the SQL admin screen too). */
export async function removeInventoryItem(providerId, itemId) {
  if (!providerId || !itemId) throw new Error("providerId and itemId are required.");
  const db = getDb();
  await remove(ref(db, `inventory/${providerId}/${itemId}`));
}
