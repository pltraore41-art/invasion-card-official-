const countdown = document.getElementById("event-countdown");
const finishedMessage = document.getElementById("countdown-finished");

if (countdown && finishedMessage) {
  const eventStart = new Date(countdown.dataset.eventStart);
  const countdownValues = {
    days: countdown.querySelector('[data-unit="days"]'),
    hours: countdown.querySelector('[data-unit="hours"]'),
    minutes: countdown.querySelector('[data-unit="minutes"]'),
    seconds: countdown.querySelector('[data-unit="seconds"]'),
  };

  function updateCountdown() {
    const remaining = eventStart.getTime() - Date.now();
    if (remaining <= 0) {
      countdown.classList.add("hidden");
      finishedMessage.classList.remove("hidden");
      return;
    }

    const totalSeconds = Math.floor(remaining / 1000);
    countdownValues.days.textContent = Math.floor(totalSeconds / 86400);
    countdownValues.hours.textContent = String(
      Math.floor((totalSeconds % 86400) / 3600),
    ).padStart(2, "0");
    countdownValues.minutes.textContent = String(
      Math.floor((totalSeconds % 3600) / 60),
    ).padStart(2, "0");
    countdownValues.seconds.textContent = String(totalSeconds % 60).padStart(
      2,
      "0",
    );
  }

  updateCountdown();
  window.setInterval(updateCountdown, 1000);
}

const supabaseConfig = window.INVASION_CONFIG || {};
const supabaseUrl = String(supabaseConfig.supabaseUrl || "").replace(/\/+$/, "");
const supabaseAnonKey = String(supabaseConfig.supabaseAnonKey || "");
const adminSessionKey = "invasion-supabase-session-v1";
let adminSession = null;
try {
  adminSession = JSON.parse(window.sessionStorage.getItem(adminSessionKey) || "null");
} catch {
  window.sessionStorage.removeItem(adminSessionKey);
}

const attendanceLabels = {
  present: "Oui, je confirme ma présence",
  absent: "Non, je ne pourrai pas venir",
  unsure: "Je ne suis pas encore sûr(e)",
};
const beverageLabels = {
  alcohol: "Boissons alcoolisées",
  non_alcoholic: "Boissons non alcoolisées",
  sweet: "Boissons non alcoolisées",
  both: "Boissons alcoolisées et non alcoolisées",
  none: "Aucune préférence",
};
const knowledgeLabels = {
  known: "Connaît déjà le cardigan",
  heard: "En a déjà entendu parler",
  new: "Découvre le cardigan",
};
const intentionLabels = {
  buy: "Acheter un cardigan — 15 000 F CFA l'unité",
  discover: "Découvrir la collection",
  inform: "Se renseigner avant un achat",
  accompany: "Accompagner un proche",
};

function requireSupabaseConfig() {
  if (!supabaseUrl || !supabaseAnonKey) {
    throw new Error("La base partagée n'est pas encore configurée. L'équipe organisatrice doit terminer la configuration Supabase.");
  }
}

function registrationFromRow(row) {
  if (!row) return null;
  return {
    reference: row.reference,
    firstName: row.first_name,
    lastName: row.last_name,
    age: row.age ?? "",
    city: row.city,
    attendance: row.attendance,
    groupType: Number(row.companions_count || 0) > 0 ? "accompanied" : "alone",
    companionsCount: Number(row.companions_count || 0),
    beverages: row.beverages,
    cardiganKnowledge: row.cardigan_knowledge,
    intention: row.intention,
    desiredSize: row.desired_size,
    cardiganQuantity: Number(row.cardigan_quantity || 0),
    preferredColor: row.preferred_color || "",
    comment: row.comment || "",
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
}

async function supabaseRequest(
  path,
  { method = "GET", body, authenticated = false, prefer, retried = false } = {},
) {
  requireSupabaseConfig();
  const headers = {
    apikey: supabaseAnonKey,
    "Content-Type": "application/json",
  };
  if (authenticated && adminSession?.access_token) {
    headers.Authorization = `Bearer ${adminSession.access_token}`;
  } else {
    headers.Authorization = `Bearer ${supabaseAnonKey}`;
  }
  if (prefer) headers.Prefer = prefer;

  const response = await fetch(`${supabaseUrl}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const responseText = await response.text();
  let result = null;
  if (responseText) {
    try {
      result = JSON.parse(responseText);
    } catch {
      throw new Error("La base de données a renvoyé une réponse illisible.");
    }
  }
  if (!response.ok) {
    if (authenticated && response.status === 401 && adminSession?.refresh_token && !retried) {
      try {
        const refreshed = await supabaseRequest("/auth/v1/token?grant_type=refresh_token", {
          method: "POST",
          body: { refresh_token: adminSession.refresh_token },
        });
        adminSession = refreshed;
        window.sessionStorage.setItem(adminSessionKey, JSON.stringify(adminSession));
        return supabaseRequest(path, { method, body, authenticated, prefer, retried: true });
      } catch {
        adminSession = null;
        window.sessionStorage.removeItem(adminSessionKey);
      }
    } else if (authenticated && response.status === 401) {
      adminSession = null;
      window.sessionStorage.removeItem(adminSessionKey);
    }
    throw new Error(result?.message || result?.msg || result?.hint || `Erreur du serveur (${response.status}).`);
  }
  return result;
}

async function loadAdminRegistrations() {
  const rows = await supabaseRequest(
    "/rest/v1/event_registrations?select=*&order=created_at.desc",
    { authenticated: true },
  );
  return rows.map(registrationFromRow);
}

async function getPublicRegistration(reference, lastName = null) {
  const row = await supabaseRequest("/rest/v1/rpc/get_cardigan_registration", {
    method: "POST",
    body: { p_reference: reference, p_last_name: lastName },
  });
  return registrationFromRow(row);
}

function showStatus(element, message, isError = false) {
  element.textContent = message;
  element.classList.toggle("status-error", isError);
}

function formValues(form) {
  return Object.fromEntries(new FormData(form).entries());
}

const registrationForm = document.getElementById("registration-form");
const registrationStatus = document.getElementById("registration-status");

function configureCompanions(groupSelect, attendanceSelect, wrapper, countInput) {
  if (!groupSelect || !attendanceSelect || !wrapper || !countInput) return;
  const update = () => {
    const needsCount = groupSelect.value === "accompanied" && attendanceSelect.value === "present";
    wrapper.classList.toggle("hidden", !needsCount);
    countInput.disabled = !needsCount;
    countInput.required = needsCount;
    if (!needsCount) countInput.value = "0";
    else if (Number(countInput.value) < 1) countInput.value = "1";
  };
  groupSelect.addEventListener("change", update);
  attendanceSelect.addEventListener("change", update);
  update();
}

function configurePurchaseQuantity(intentionSelect, purchaseBox, quantityInput, totalLabel) {
  if (!intentionSelect || !purchaseBox || !quantityInput || !totalLabel) return;
  const update = () => {
    const buying = intentionSelect.value === "buy";
    purchaseBox.classList.toggle("hidden", !buying);
    quantityInput.disabled = !buying;
    quantityInput.required = buying;
    if (buying && Number(quantityInput.value) < 1) quantityInput.value = "1";
    if (!buying) quantityInput.value = "0";
    const quantity = buying ? Number(quantityInput.value || 1) : 0;
    totalLabel.textContent = buying
      ? `Estimation indicative : ${(quantity * 15000).toLocaleString("fr-FR")} F CFA`
      : "";
  };
  intentionSelect.addEventListener("change", update);
  quantityInput.addEventListener("input", update);
  update();
}

if (registrationForm && registrationStatus) {
  const groupType = document.getElementById("group-type");
  const attendance = document.getElementById("attendance");
  const companionsInput = document.getElementById("companions-count");
  configureCompanions(
    groupType,
    attendance,
    document.getElementById("companions-wrapper"),
    companionsInput,
  );
  const intention = document.getElementById("intention");
  const cardiganQuantity = document.getElementById("cardigan-quantity");
  configurePurchaseQuantity(
    intention,
    document.getElementById("purchase-box"),
    cardiganQuantity,
    document.getElementById("purchase-total"),
  );
  registrationForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    showStatus(registrationStatus, "");
    try {
      const values = formValues(registrationForm);
      const row = await supabaseRequest("/rest/v1/rpc/register_cardigan", {
        method: "POST",
        body: {
          p_data: {
            ...values,
            age: values.age ? Number(values.age) : null,
            companionsCount: values.groupType === "accompanied" && values.attendance === "present"
              ? Number(values.companionsCount || 1)
              : 0,
            cardiganQuantity: values.intention === "buy" ? Number(values.cardiganQuantity || 1) : 0,
          },
        },
      });
      const registration = registrationFromRow(row);
      window.location.href = `billet.html?reference=${encodeURIComponent(registration.reference)}&new=1`;
    } catch (error) {
      showStatus(registrationStatus, `Impossible d'enregistrer l'inscription : ${error.message}`, true);
    }
  });
}

const rsvpForm = document.getElementById("rsvp-form");
const rsvpStatus = document.getElementById("rsvp-status");

if (rsvpForm && rsvpStatus) {
  const groupType = document.getElementById("rsvp-group-type");
  const attendance = document.getElementById("rsvp-attendance");
  const companionsInput = document.getElementById("rsvp-companions");
  configureCompanions(
    groupType,
    attendance,
    document.getElementById("rsvp-companions-wrapper"),
    companionsInput,
  );
  let lookupVersion = 0;
  const prefillRsvp = async () => {
    const version = ++lookupVersion;
    try {
      const reference = document.getElementById("rsvp-reference").value.trim().toLowerCase();
      const lastName = document.getElementById("rsvp-last-name").value.trim();
      if (!reference || !lastName) return;
      const registration = await getPublicRegistration(reference, lastName);
      if (version !== lookupVersion) return;
      if (!registration) return;
      attendance.value = registration.attendance;
      groupType.value = Number(registration.companionsCount || 0) > 0 ? "accompanied" : "alone";
      companionsInput.value = String(registration.companionsCount || 0);
      attendance.dispatchEvent(new Event("change"));
      groupType.dispatchEvent(new Event("change"));
      showStatus(rsvpStatus, "Inscription retrouvée : vérifiez puis confirmez votre réponse.");
    } catch (error) {
      showStatus(rsvpStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  };
  document.getElementById("rsvp-reference").addEventListener("change", prefillRsvp);
  document.getElementById("rsvp-last-name").addEventListener("change", prefillRsvp);
  rsvpForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    showStatus(rsvpStatus, "");
    try {
      const values = formValues(rsvpForm);
      const row = await supabaseRequest("/rest/v1/rpc/update_cardigan_rsvp", {
        method: "POST",
        body: {
          p_reference: values.reference.trim(),
          p_last_name: values.lastName.trim(),
          p_attendance: values.attendance,
          p_group_type: values.groupType,
          p_companions_count: values.groupType === "accompanied" && values.attendance === "present"
            ? Number(values.companionsCount || 1)
            : 0,
        },
      });
      const registration = registrationFromRow(row);
      if (!registration) {
        showStatus(rsvpStatus, "Inscription introuvable. Vérifiez la référence et le nom saisis.", true);
        return;
      }

      window.location.href = `billet.html?reference=${encodeURIComponent(registration.reference)}&updated=1`;
    } catch (error) {
      showStatus(rsvpStatus, `Impossible de mettre à jour votre réponse : ${error.message}`, true);
    }
  });
}

const ticketLookupForm = document.getElementById("ticket-lookup-form");
const ticketCard = document.getElementById("ticket-card");
const ticketStatus = document.getElementById("ticket-status");
let displayedRegistration = null;

async function displayTicket(reference) {
  const registration = await getPublicRegistration(reference.trim());
  if (!registration) {
    ticketCard.classList.add("hidden");
    showStatus(ticketStatus, "Aucune inscription avec cette référence n'a été trouvée.", true);
    return false;
  }

  displayedRegistration = registration;
  const participantName = `${registration.firstName} ${registration.lastName}`.trim();
  document.getElementById("ticket-name").textContent = participantName;
  document.getElementById("ticket-holder-name").textContent = participantName;
  document.getElementById("ticket-reference-value").textContent = registration.reference;
  document.getElementById("ticket-attendance").textContent =
    attendanceLabels[registration.attendance] || "Réponse non renseignée";
  document.getElementById("ticket-companions").textContent =
    registration.attendance === "present"
      ? registration.companionsCount > 0
        ? `Accompagné(e) de ${registration.companionsCount} personne(s)`
        : "Seul(e)"
      : "—";
  document.getElementById("ticket-city").textContent = registration.city || "—";
  document.getElementById("ticket-size").textContent = registration.desiredSize || "Non renseignée";
  document.getElementById("ticket-beverages").textContent =
    beverageLabels[registration.beverages] || "Non renseignée";
  document.getElementById("ticket-intention").textContent =
    intentionLabels[registration.intention] || "Non renseigné";
  document.getElementById("ticket-quantity").textContent =
    registration.intention === "buy"
      ? `${registration.cardiganQuantity || 1} × 15 000 F CFA`
      : "Pas d'achat prévu pour le moment";
  document.getElementById("qr-reference-label").textContent = registration.reference;
  const qrContainer = document.getElementById("ticket-qr");
  qrContainer.replaceChildren();
  if (typeof QRCode === "undefined") {
    throw new Error("Le générateur du code QR n'a pas pu être chargé.");
  }
  new QRCode(qrContainer, {
    text: `INVASION CARDIGAN 2026|REFERENCE:${registration.reference}`,
    width: 360,
    height: 360,
    colorDark: "#101010",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H,
  });
  ticketCard.classList.remove("hidden");
  showStatus(
    ticketStatus,
    new URLSearchParams(window.location.search).has("new")
      ? "Votre inscription est enregistrée. Conservez cette référence."
      : new URLSearchParams(window.location.search).has("updated")
        ? "Votre réponse a été mise à jour."
        : "",
  );
  ticketCard.scrollIntoView({ behavior: "smooth", block: "center" });
  return true;
}

if (ticketLookupForm && ticketCard && ticketStatus) {
  ticketLookupForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
      await displayTicket(formValues(ticketLookupForm).reference);
    } catch (error) {
      showStatus(ticketStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  });

  const reference = new URLSearchParams(window.location.search).get("reference");
  if (reference) {
    document.getElementById("ticket-reference").value = reference;
    try {
      displayTicket(reference).catch((error) => {
        showStatus(ticketStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
      });
    } catch (error) {
      showStatus(ticketStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  }
}

function ticketText(registration) {
  return [
    "INVASION DE CARDIGAN — ÉVÉNEMENT DE LANCEMENT",
    "14 novembre 2026",
    "",
    `Participant(e) : ${registration.firstName} ${registration.lastName}`,
    `Référence : ${registration.reference}`,
    `Présence : ${attendanceLabels[registration.attendance] || "Non renseignée"}`,
    `Groupe : ${registration.companionsCount > 0 ? `Accompagné(e) de ${registration.companionsCount} personne(s)` : "Seul(e)"}`,
    `Boissons : ${beverageLabels[registration.beverages] || "Non renseignée"}`,
    `Taille : ${registration.desiredSize || "Non renseignée"}`,
    `Objectif : ${intentionLabels[registration.intention] || "Non renseigné"}`,
    "Prix unitaire cardigan : 15 000 F CFA",
    `Quantité envisagée : ${registration.intention === "buy" ? registration.cardiganQuantity || 1 : 0}`,
    "",
    "Inscription enregistrée dans la base partagée Invasion de Cardigan.",
  ].join("\n");
}

document.getElementById("download-ticket")?.addEventListener("click", () => {
  try {
    if (!displayedRegistration) throw new Error("Aucune inscription n'est affichée.");
    const file = new Blob([ticketText(displayedRegistration)], { type: "text/plain;charset=utf-8" });
    const url = URL.createObjectURL(file);
    const link = document.createElement("a");
    link.href = url;
    link.download = `invasion-${displayedRegistration.reference.toLowerCase()}.txt`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    showStatus(ticketStatus, `Impossible de créer le récapitulatif : ${error.message}`, true);
  }
});

document.getElementById("download-qr")?.addEventListener("click", async () => {
  const canvas = document.querySelector("#ticket-qr canvas");
  const logo = document.querySelector(".qr-center-logo");
  if (!canvas || !logo || !displayedRegistration) {
    showStatus(ticketStatus, "Le code QR n'est pas encore disponible.", true);
    return;
  }
  try {
    const output = document.createElement("canvas");
    output.width = 900;
    output.height = 1260;
    const context = output.getContext("2d");
    if (!context) throw new Error("Le navigateur ne peut pas préparer l'image.");
    if (!logo.complete || logo.naturalWidth === 0) {
      throw new Error("Le logo Invasion n'est pas encore chargé. Réessayez dans un instant.");
    }

    const background = context.createLinearGradient(0, 0, output.width, output.height);
    background.addColorStop(0, "#29251d");
    background.addColorStop(1, "#101010");
    context.fillStyle = background;
    context.fillRect(0, 0, output.width, output.height);
    context.strokeStyle = "#d5b97a";
    context.lineWidth = 5;
    context.strokeRect(24, 24, output.width - 48, output.height - 48);

    context.drawImage(logo, 240, 76, 420, 145);
    context.fillStyle = "#e8d39b";
    context.font = "700 24px Segoe UI, sans-serif";
    context.textAlign = "center";
    context.letterSpacing = "5px";
    context.fillText("INVITATION OFFICIELLE", output.width / 2, 264);
    context.letterSpacing = "0px";

    context.fillStyle = "#c9c1b2";
    context.font = "700 20px Segoe UI, sans-serif";
    context.letterSpacing = "4px";
    context.fillText("BILLET AU NOM DE", output.width / 2, 328);
    context.fillStyle = "#ffffff";
    context.font = "800 42px Segoe UI, sans-serif";
    context.letterSpacing = "0px";
    const participantName = `${displayedRegistration.firstName} ${displayedRegistration.lastName}`.trim();
    context.fillText(participantName, output.width / 2, 388, 760);

    const qrSize = 540;
    const qrX = (output.width - qrSize) / 2;
    const qrY = 438;
    context.fillStyle = "#ffffff";
    context.fillRect(qrX - 18, qrY - 18, qrSize + 36, qrSize + 36);
    context.drawImage(canvas, qrX, qrY, qrSize, qrSize);

    const center = output.width / 2;
    const logoBoxWidth = 152;
    const logoBoxHeight = 104;
    const logoBoxX = center - logoBoxWidth / 2;
    const logoBoxY = qrY + qrSize / 2 - logoBoxHeight / 2;
    context.fillStyle = "#111111";
    context.fillRect(logoBoxX, logoBoxY, logoBoxWidth, logoBoxHeight);
    context.strokeStyle = "#d5b97a";
    context.lineWidth = 4;
    context.strokeRect(logoBoxX, logoBoxY, logoBoxWidth, logoBoxHeight);
    context.drawImage(logo, logoBoxX + 12, logoBoxY + 25, logoBoxWidth - 24, logoBoxHeight - 50);

    context.fillStyle = "#f2e6c6";
    context.font = "800 32px Segoe UI, sans-serif";
    context.letterSpacing = "5px";
    context.fillText(displayedRegistration.reference, output.width / 2, 1085);
    context.letterSpacing = "2px";
    context.fillStyle = "#c9c1b2";
    context.font = "600 17px Segoe UI, sans-serif";
    context.fillText("PRÉSENTEZ CE CODE À L'ACCUEIL", output.width / 2, 1140);
    context.letterSpacing = "1px";
    context.fillText("Événement de lancement · 14 novembre 2026", output.width / 2, 1180);

    const blob = await new Promise((resolve, reject) => {
      output.toBlob((blob) => {
        if (!blob) {
          reject(new Error("Le navigateur n'a pas pu créer l'image du billet."));
          return;
        }
        resolve(blob);
      }, "image/png");
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `invasion-${displayedRegistration.reference.toLowerCase()}-billet.png`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    showStatus(ticketStatus, `Impossible de télécharger le billet : ${error.message}`, true);
  }
});

document.getElementById("print-ticket")?.addEventListener("click", () => window.print());

const adminTable = document.getElementById("admin-registrations");
const adminStatus = document.getElementById("admin-status");
const adminLoginForm = document.getElementById("admin-login-form");
const adminLoginPanel = document.getElementById("admin-login-panel");
const adminContent = document.getElementById("admin-content");
let adminRegistrations = [];
let closeAdminView = () => {};

if (adminLoginForm && adminLoginPanel && adminContent) {
  const loginStatus = document.getElementById("admin-login-status");
  const setAdminUnlocked = (unlocked) => {
    adminLoginPanel.classList.toggle("hidden", unlocked);
    adminContent.hidden = !unlocked;
    document.body.classList.toggle("admin-locked", !unlocked);
  };
  closeAdminView = () => setAdminUnlocked(false);
  setAdminUnlocked(false);

  if (!supabaseUrl || !supabaseAnonKey) {
    showStatus(loginStatus, "La base partagée n'est pas configurée. L'équipe organisatrice doit terminer la configuration Supabase.", true);
  } else if (adminSession?.access_token) {
    supabaseRequest("/auth/v1/user", { authenticated: true })
      .then(async () => {
        const isAdmin = await supabaseRequest("/rest/v1/rpc/is_cardigan_admin", {
          method: "POST",
          body: {},
          authenticated: true,
        });
        if (isAdmin !== true) throw new Error("Ce compte n'a pas le rôle administrateur.");
      })
      .then(() => {
        setAdminUnlocked(true);
        return refreshAdminList();
      })
      .catch((error) => {
        setAdminUnlocked(false);
        showStatus(loginStatus, `Session administrateur expirée. Reconnectez-vous. (${error.message})`, true);
      });
  }

  adminLoginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    showStatus(loginStatus, "");
    try {
      const { email, password } = formValues(adminLoginForm);
      const response = await supabaseRequest("/auth/v1/token?grant_type=password", {
        method: "POST",
        body: { email, password },
      });
      adminSession = response;
      window.sessionStorage.setItem(adminSessionKey, JSON.stringify(adminSession));
      const isAdmin = await supabaseRequest("/rest/v1/rpc/is_cardigan_admin", {
        method: "POST",
        body: {},
        authenticated: true,
      });
      if (isAdmin !== true) throw new Error("Ce compte n'a pas le rôle administrateur.");
      setAdminUnlocked(true);
      adminLoginForm.reset();
      await refreshAdminList();
    } catch (error) {
      adminSession = null;
      window.sessionStorage.removeItem(adminSessionKey);
      setAdminUnlocked(false);
      showStatus(loginStatus, `Connexion impossible : ${error.message}`, true);
    }
  });

  document.getElementById("admin-lock").addEventListener("click", async () => {
    let logoutError = null;
    try {
      if (adminSession?.access_token) {
        await supabaseRequest("/auth/v1/logout", { method: "POST", authenticated: true });
      }
    } catch (error) {
      logoutError = error;
    }
    adminSession = null;
    window.sessionStorage.removeItem(adminSessionKey);
    adminRegistrations = [];
    closeAdminView();
    showStatus(
      adminStatus,
      logoutError ? `Session verrouillée sur cet appareil, mais la déconnexion distante a échoué : ${logoutError.message}` : "",
      Boolean(logoutError),
    );
  });
}

function registrationGroup(registration) {
  return Number(registration.companionsCount || 0) > 0 ? "Accompagné(e)" : "Seul(e)";
}

function adminCell(row, value) {
  const cell = document.createElement("td");
  cell.textContent = value || "—";
  row.append(cell);
  return cell;
}

function renderAdminList() {
  if (!adminTable) return;
  const search = document.getElementById("admin-search").value.trim().toLocaleLowerCase("fr");
  const filter = document.getElementById("admin-filter").value;
  const presentCount = adminRegistrations.filter((item) => item.attendance === "present").length;
  const expectedPeople = adminRegistrations.reduce(
    (total, item) => total + (item.attendance === "present" ? 1 + Number(item.companionsCount || 0) : 0),
    0,
  );
  document.getElementById("admin-total").textContent = String(adminRegistrations.length);
  document.getElementById("admin-present").textContent = String(presentCount);
  document.getElementById("admin-people").textContent = String(expectedPeople);

  const visible = adminRegistrations.filter((item) => {
    const searchable = `${item.firstName} ${item.lastName} ${item.city} ${item.reference}`.toLocaleLowerCase("fr");
    return (!search || searchable.includes(search)) && (!filter || item.attendance === filter);
  });
  adminTable.replaceChildren();
  for (const registration of visible) {
    const row = document.createElement("tr");
    adminCell(row, `${registration.reference} — ${registration.firstName} ${registration.lastName}`);
    adminCell(row, registration.city);
    adminCell(row, attendanceLabels[registration.attendance] || "Non renseignée");
    adminCell(
      row,
      registration.attendance === "present" && Number(registration.companionsCount || 0) > 0
        ? `${registrationGroup(registration)} · ${registration.companionsCount} personne(s)`
        : registration.attendance === "present"
          ? "Seul(e)"
          : "—",
    );
    adminCell(row, beverageLabels[registration.beverages] || "Non renseignée");
    adminCell(row, knowledgeLabels[registration.cardiganKnowledge] || "Non renseignée");
    adminCell(row, intentionLabels[registration.intention] || "Non renseigné");
    const actions = document.createElement("td");
    const edit = document.createElement("button");
    edit.type = "button";
    edit.className = "btn btn-secondary btn-sm";
    edit.dataset.action = "edit";
    edit.dataset.reference = registration.reference;
    edit.textContent = "Modifier";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "btn btn-danger btn-sm";
    remove.dataset.action = "delete";
    remove.dataset.reference = registration.reference;
    remove.textContent = "Supprimer";
    actions.append(edit, remove);
    row.append(actions);
    adminTable.append(row);
  }
  document.getElementById("admin-visible-count").textContent =
    `${visible.length} résultat${visible.length === 1 ? "" : "s"}`;
  document.getElementById("admin-empty").classList.toggle("hidden", visible.length !== 0);
}

async function refreshAdminList() {
  try {
    adminRegistrations = await loadAdminRegistrations();
    renderAdminList();
    showStatus(adminStatus, "");
  } catch (error) {
    if (!adminSession?.access_token) {
      adminRegistrations = [];
      renderAdminList();
      closeAdminView();
    }
    showStatus(adminStatus, `Impossible de charger les inscriptions partagées : ${error.message}`, true);
  }
}

if (adminTable && adminStatus) {
  const editPanel = document.getElementById("admin-edit-panel");
  const editForm = document.getElementById("admin-edit-form");
  const editGroupType = document.getElementById("edit-group-type");
  const editAttendance = document.getElementById("edit-attendance");
  const editCompanions = document.getElementById("edit-companions");
  const updateEditCompanions = () => {
    const enabled = editGroupType.value === "accompanied" && editAttendance.value === "present";
    editCompanions.disabled = !enabled;
    editCompanions.required = enabled;
    if (enabled && Number(editCompanions.value) < 1) editCompanions.value = "1";
    if (!enabled) editCompanions.value = "0";
  };

  document.getElementById("admin-search").addEventListener("input", renderAdminList);
  document.getElementById("admin-filter").addEventListener("change", renderAdminList);
  editGroupType.addEventListener("change", updateEditCompanions);
  editAttendance.addEventListener("change", updateEditCompanions);

  adminTable.addEventListener("click", async (event) => {
    try {
      const button = event.target.closest("button[data-action]");
      if (!button) return;
      const index = adminRegistrations.findIndex((item) => item.reference === button.dataset.reference);
      if (index < 0) {
        showStatus(adminStatus, "Cette inscription n'existe plus.", true);
        await refreshAdminList();
        return;
      }

      if (button.dataset.action === "delete") {
        const registration = adminRegistrations[index];
        if (!window.confirm(`Supprimer l'inscription de ${registration.firstName} ${registration.lastName} ?`)) return;
        await supabaseRequest(
          `/rest/v1/event_registrations?reference=eq.${encodeURIComponent(registration.reference)}`,
          { method: "DELETE", authenticated: true, prefer: "return=minimal" },
        );
        showStatus(adminStatus, "Inscription supprimée de la base partagée.");
        await refreshAdminList();
        return;
      }

      const registration = adminRegistrations[index];
      for (const field of editForm.elements) {
        if (!field.name) continue;
        if (field.name === "groupType") {
          field.value = registrationGroup(registration) === "Accompagné(e)" ? "accompanied" : "alone";
        } else if (field.name === "companionsCount") {
          field.value = String(registration.companionsCount || 0);
        } else if (field.name === "beverages") {
          field.value = registration.beverages === "sweet" ? "non_alcoholic" : registration.beverages || "none";
        } else if (field.name === "cardiganQuantity") {
          field.value = String(registration.cardiganQuantity || 0);
        } else {
          field.value = registration[field.name] ?? "";
        }
      }
      if (editForm.elements.groupType.value === "accompanied" && editForm.elements.attendance.value === "present") {
        editForm.elements.companionsCount.value = String(Math.max(1, Number(editForm.elements.companionsCount.value)));
      }
      updateEditCompanions();
      editPanel.classList.remove("hidden");
      editPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    } catch (error) {
      showStatus(adminStatus, `Impossible d'effectuer cette action : ${error.message}`, true);
    }
  });

  editForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
      const values = formValues(editForm);
      const registration = adminRegistrations.find((item) => item.reference === values.reference);
      if (!registration) throw new Error("Inscription introuvable.");
      const updated = {
        first_name: values.firstName.trim(),
        last_name: values.lastName.trim(),
        city: values.city.trim(),
        age: values.age ? Number(values.age) : null,
        attendance: values.attendance,
        companions_count: values.attendance === "present" && values.groupType === "accompanied"
          ? Number(values.companionsCount || 1)
          : 0,
        beverages: values.beverages,
        cardigan_knowledge: values.cardiganKnowledge,
        intention: values.intention,
        desired_size: values.desiredSize.trim(),
        cardigan_quantity: values.intention === "buy" ? Number(values.cardiganQuantity || 1) : 0,
        preferred_color: values.preferredColor.trim() || null,
        comment: values.comment.trim() || null,
        updated_at: new Date().toISOString(),
      };
      await supabaseRequest(
        `/rest/v1/event_registrations?reference=eq.${encodeURIComponent(registration.reference)}`,
        {
          method: "PATCH",
          body: updated,
          authenticated: true,
          prefer: "return=minimal",
        },
      );
      editPanel.classList.add("hidden");
      showStatus(adminStatus, "Modifications enregistrées dans la base partagée.");
      await refreshAdminList();
    } catch (error) {
      showStatus(adminStatus, `Impossible d'enregistrer : ${error.message}`, true);
    }
  });

  document.getElementById("admin-cancel-edit").addEventListener("click", () => {
    editPanel.classList.add("hidden");
  });
  document.getElementById("admin-export").addEventListener("click", () => {
    try {
      const registrations = adminRegistrations;
      const columns = [
        ["reference", "Référence"], ["firstName", "Prénom"], ["lastName", "Nom"],
        ["city", "Ville"], ["age", "Âge"], ["attendance", "Présence"],
        ["groupType", "Groupe"], ["companionsCount", "Accompagnants"],
        ["beverages", "Boissons"],
        ["cardiganKnowledge", "Connaissance du cardigan"], ["intention", "Objectif"],
        ["cardiganQuantity", "Quantité envisagée"],
        ["desiredSize", "Taille"], ["preferredColor", "Couleur ou modèle"],
        ["comment", "Commentaire"], ["createdAt", "Inscrit le"],
      ];
      const csvValue = (value) => {
        const text = String(value ?? "");
        const safeText = /^[=+\-@]/.test(text) ? `'${text}` : text;
        return `"${safeText.replaceAll('"', '""')}"`;
      };
      const exportValue = (registration, key) => {
        if (key === "attendance") return attendanceLabels[registration.attendance] || registration.attendance;
        if (key === "groupType") {
          return registration.groupType === "accompanied" || Number(registration.companionsCount || 0) > 0
            ? "Accompagné(e)"
            : "Seul(e)";
        }
        if (key === "beverages") return beverageLabels[registration.beverages] || registration.beverages;
        if (key === "cardiganKnowledge") {
          return knowledgeLabels[registration.cardiganKnowledge] || registration.cardiganKnowledge;
        }
        if (key === "intention") return intentionLabels[registration.intention] || registration.intention;
        return registration[key];
      };
      const csv = [
        columns.map(([, title]) => csvValue(title)).join(";"),
        ...registrations.map((item) => columns.map(([key]) => csvValue(exportValue(item, key))).join(";")),
      ].join("\r\n");
      const file = new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(file);
      const link = document.createElement("a");
      link.href = url;
      link.download = "invasion-inscriptions.csv";
      link.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      showStatus(adminStatus, `Impossible d'exporter la liste : ${error.message}`, true);
    }
  });
  document.getElementById("admin-print").addEventListener("click", () => window.print());
}
