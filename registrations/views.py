import csv
import io
import base64
import html
from functools import wraps
from pathlib import Path

import qrcode
from django.contrib import messages
from django.contrib.auth import login
from django.contrib.auth.views import LoginView, LogoutView
from django.db import transaction
from django.db.models import Count, Q, Sum
from django.http import HttpResponse, HttpResponseForbidden
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse
from django.utils import timezone
from django.views.decorators.http import require_GET, require_http_methods, require_POST
from qrcode.image.svg import SvgPathImage

from .forms import AdminRegistrationForm, OrganizerAuthenticationForm, ParticipationForm, RegistrationForm
from .models import EventRegistration


BRAND_NAME = "Invasion de Cardigan"
BRAND_TAGLINE = "Lancement officiel de la marque"
LAUNCH_EVENT = "Événement de lancement Invasion de Cardigan"
EVENT_DATE = "14 novembre 2026"
EVENT_START = "2026-11-14T00:00:00+01:00"


def organizer_required(view_func):
    @wraps(view_func)
    def wrapped(request, *args, **kwargs):
        if not request.user.is_authenticated:
            return redirect(f"{reverse('admin_login')}?next={request.get_full_path()}")
        if not request.user.is_staff:
            return HttpResponseForbidden("Accès réservé à l'équipe organisatrice.")
        return view_func(request, *args, **kwargs)

    return wrapped


def page_context(**values):
    return {
        "brand_name": BRAND_NAME,
        "brand_tagline": BRAND_TAGLINE,
        "launch_event": LAUNCH_EVENT,
        "event_date": EVENT_DATE,
        **values,
    }


def registration_stats():
    rows = EventRegistration.objects.all()
    totals = rows.aggregate(
        total=Count("pk"),
        present=Count("pk", filter=Q(attendance=EventRegistration.Attendance.PRESENT)),
        unsure=Count("pk", filter=Q(attendance=EventRegistration.Attendance.UNSURE)),
        absent=Count("pk", filter=Q(attendance=EventRegistration.Attendance.ABSENT)),
        present_registrations=Count(
            "pk", filter=Q(attendance=EventRegistration.Attendance.PRESENT)
        ),
        companions=Sum(
            "companions_count", filter=Q(attendance=EventRegistration.Attendance.PRESENT)
        ),
        potential_buyers=Count(
            "pk", filter=Q(intention=EventRegistration.Intention.BUY)
        ),
        discoverers=Count(
            "pk", filter=Q(intention=EventRegistration.Intention.DISCOVER)
        ),
    )
    totals["expected_attendees"] = totals.pop("present_registrations") + (totals.pop("companions") or 0)
    totals["beverage_counts"] = {
        row["beverages"]: row["count"]
        for row in rows.values("beverages").annotate(count=Count("pk"))
    }
    totals["knowledge_counts"] = {
        row["cardigan_knowledge"]: row["count"]
        for row in rows.values("cardigan_knowledge").annotate(count=Count("pk"))
    }
    totals["city_counts"] = {
        row["city"]: row["count"]
        for row in rows.exclude(city="").values("city").annotate(count=Count("pk")).order_by("city")
    }
    return totals


@require_GET
def home(request):
    return render(
        request,
        "registrations/home.html",
        page_context(stats=registration_stats(), event_start=EVENT_START),
    )


@require_http_methods(["GET", "POST"])
def registration_create(request):
    form = RegistrationForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        registration = form.save()
        messages.success(request, f"Votre inscription est enregistrée. Référence : {registration.reference}")
        return redirect("registration_detail", reference=registration.reference)
    return render(
        request,
        "registrations/registration_form.html",
        page_context(title="Inscription", form=form),
    )


@require_GET
def registration_detail(request, reference):
    registration = get_object_or_404(EventRegistration, reference__iexact=reference)
    return render(
        request,
        "registrations/registration_detail.html",
        page_context(title=f"Invitation {registration.reference}", registration=registration),
    )


@require_GET
def registration_ticket(request, reference):
    registration = get_object_or_404(EventRegistration, reference__iexact=reference)
    check_in_url = request.build_absolute_uri(
        reverse("admin_check_in", args=[registration.reference])
    )
    qr = qrcode.QRCode(box_size=8, border=4, error_correction=qrcode.constants.ERROR_CORRECT_H)
    qr.add_data(check_in_url)
    qr.make(fit=True)
    svg = qr.make_image(image_factory=SvgPathImage)
    qr_svg = svg.to_string()
    if isinstance(qr_svg, bytes):
        qr_svg = qr_svg.decode("utf-8")
    logo_path = Path(__file__).resolve().parent.parent / "app" / "static" / "invasion-logo.png"
    logo_data = base64.b64encode(logo_path.read_bytes()).decode("ascii")
    qr_data = base64.b64encode(qr_svg.encode("utf-8")).decode("ascii")
    full_name = html.escape(registration.full_name)
    ticket_reference = html.escape(registration.reference)
    attendance = html.escape(registration.get_attendance_display())
    ticket = f"""<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="1120" viewBox="0 0 900 1120" role="img" aria-label="Billet d'accès de {full_name}">
  <rect width="900" height="1120" fill="#050505"/>
  <rect x="24" y="24" width="852" height="1072" rx="28" fill="none" stroke="#f5f5f5" stroke-width="3"/>
  <image x="205" y="62" width="490" height="170" href="data:image/png;base64,{logo_data}" preserveAspectRatio="xMidYMid meet"/>
  <g fill="#fff" text-anchor="middle" font-family="Arial, Helvetica, sans-serif">
    <text x="450" y="305" font-size="42">BILLET D'ACCÈS</text>
    <text x="450" y="355" font-size="23" fill="#c6c6c6">INVITATION OFFICIELLE</text>
    <path d="M90 385h720" stroke="#555" stroke-width="2"/>
    <text x="450" y="450" font-size="30">{full_name}</text>
    <text x="450" y="500" font-size="23" fill="#d0d0d0">{ticket_reference}</text>
    <text x="450" y="545" font-size="23" fill="#c6c6c6">{EVENT_DATE}</text>
    <text x="450" y="585" font-size="23">{attendance}</text>
  </g>
  <image x="240" y="615" width="420" height="420" href="data:image/svg+xml;base64,{qr_data}"/>
  <rect x="367" y="778" width="166" height="94" rx="12" fill="#fff"/>
  <image x="387" y="798" width="126" height="54" href="data:image/png;base64,{logo_data}" preserveAspectRatio="xMidYMid meet" style="filter: brightness(0)"/>
  <text x="450" y="1065" fill="#c6c6c6" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="23">PRESENTEZ CE QR CODE A L'ENTREE</text>
</svg>"""
    response = HttpResponse(ticket, content_type="image/svg+xml; charset=utf-8")
    disposition = "attachment" if request.GET.get("download") == "1" else "inline"
    response["Content-Disposition"] = (
        f'{disposition}; filename="ticket-{registration.reference}.svg"'
    )
    response["X-Content-Type-Options"] = "nosniff"
    return response


@require_http_methods(["GET", "POST"])
def participation(request):
    form = ParticipationForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        registration = form.cleaned_data["registration"]
        registration.attendance = form.cleaned_data["attendance"]
        registration.companions_count = form.cleaned_data["companions_count"]
        registration.save(update_fields=["attendance", "companions_count", "budget", "updated_at"])
        messages.success(request, "Votre réponse a bien été enregistrée. Merci !")
        return redirect("registration_detail", reference=registration.reference)
    return render(
        request,
        "registrations/participation.html",
        page_context(title="Participation", form=form),
    )


@require_GET
@organizer_required
def registration_list(request):
    registrations = EventRegistration.objects.all()
    search = request.GET.get("q", "").strip()
    city = request.GET.get("ville", "").strip()
    attendance = request.GET.get("presence", "").strip()
    intention = request.GET.get("intention", "").strip()
    beverage = request.GET.get("boisson", "").strip()

    if search:
        registrations = registrations.filter(
            Q(first_name__icontains=search) | Q(last_name__icontains=search)
        )
    if city:
        registrations = registrations.filter(city__icontains=city)
    if attendance in EventRegistration.Attendance.values:
        registrations = registrations.filter(attendance=attendance)
    else:
        attendance = ""
    if intention in EventRegistration.Intention.values:
        registrations = registrations.filter(intention=intention)
    else:
        intention = ""
    if beverage in EventRegistration.Beverage.values:
        registrations = registrations.filter(beverages=beverage)
    else:
        beverage = ""
    return render(
        request,
        "registrations/registration_list.html",
        page_context(
            title="Inscriptions",
            registrations=registrations,
            search=search,
            city_filter=city,
            current_attendance=attendance,
            current_intention=intention,
            current_beverage=beverage,
            attendance_choices=EventRegistration.Attendance.choices,
            intention_choices=EventRegistration.Intention.choices,
            beverage_choices=EventRegistration.Beverage.choices,
        ),
    )


class OrganizerLoginView(LoginView):
    authentication_form = OrganizerAuthenticationForm
    template_name = "registrations/admin_login.html"
    redirect_authenticated_user = True

    def get_success_url(self):
        if not self.request.user.is_staff:
            return reverse("home")
        return super().get_success_url()

    def form_valid(self, form):
        if not form.get_user().is_staff:
            form.add_error(None, "Ce compte n'a pas accès à l'espace organisateur.")
            return self.form_invalid(form)
        return super().form_valid(form)


class OrganizerLogoutView(LogoutView):
    next_page = "home"


@require_GET
@organizer_required
def admin_dashboard(request):
    registrations = EventRegistration.objects.all()
    return render(
        request,
        "registrations/admin_dashboard.html",
        page_context(
            title="Administration",
            registrations=registrations,
            stats=registration_stats(),
            beverage_choices=EventRegistration.Beverage.choices,
            knowledge_choices=EventRegistration.CardiganKnowledge.choices,
        ),
    )


@require_GET
@organizer_required
def export_csv(request):
    output = io.StringIO(newline="")
    writer = csv.writer(output)
    writer.writerow(
        [
            "reference",
            "nom",
            "prenom",
            "age",
            "ville",
            "presence",
            "accompagnants",
            "quantite_cardigans",
            "boissons",
            "connaissance",
            "objectif",
            "taille",
            "couleur",
            "budget",
            "commentaire",
            "created_at",
        ]
    )
    for registration in EventRegistration.objects.all():
        cells = [
            registration.reference,
            registration.last_name,
            registration.first_name,
            registration.age if registration.age is not None else "",
            registration.city,
            registration.get_attendance_display(),
            registration.companions_count,
            registration.cardigan_quantity,
            registration.get_beverages_display(),
            registration.get_cardigan_knowledge_display(),
            registration.get_intention_display(),
            registration.desired_size,
            registration.preferred_color,
            registration.budget,
            registration.comment,
            timezone.localtime(registration.created_at).isoformat(),
        ]
        writer.writerow(
            [
                f"'{cell}" if isinstance(cell, str) and cell.startswith(("=", "+", "-", "@")) else cell
                for cell in cells
            ]
        )
    response = HttpResponse("\ufeff" + output.getvalue(), content_type="text/csv; charset=utf-8")
    response["Content-Disposition"] = 'attachment; filename="inscriptions-cardigan.csv"'
    return response


@require_http_methods(["GET", "POST"])
@organizer_required
def admin_registration(request, reference):
    registration = get_object_or_404(EventRegistration, reference__iexact=reference)
    form = AdminRegistrationForm(request.POST or None, instance=registration)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, "Inscription mise à jour.")
        return redirect("admin_registration", reference=registration.reference)
    return render(
        request,
        "registrations/admin_detail.html",
        page_context(title="Fiche participant", registration=registration, form=form),
    )


@require_POST
@organizer_required
def delete_registration(request, reference):
    registration = get_object_or_404(EventRegistration, reference__iexact=reference)
    registration.delete()
    messages.success(request, "L'inscription a bien été supprimée.")
    return redirect("admin_dashboard")


@require_http_methods(["GET", "POST"])
@organizer_required
def admin_check_in(request, reference):
    if request.method == "POST":
        with transaction.atomic():
            registration = get_object_or_404(
                EventRegistration.objects.select_for_update(), reference__iexact=reference
            )
            if registration.checked_in_at is None:
                registration.checked_in_at = timezone.now()
                registration.save(update_fields=["checked_in_at"])
                messages.success(request, f"Pointage enregistré pour {registration.full_name}.")
            else:
                messages.error(request, "Ce billet a déjà été pointé.")
        return redirect("admin_check_in", reference=registration.reference)
    registration = get_object_or_404(EventRegistration, reference__iexact=reference)
    return render(
        request,
        "registrations/admin_check_in.html",
        page_context(
            title="Pointage participant",
            registration=registration,
            checked_in_at=registration.checked_in_at,
        ),
    )
