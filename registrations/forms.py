from django import forms
from django.contrib.auth.forms import AuthenticationForm
from django.core.exceptions import ValidationError

from .models import EventRegistration


class RegistrationForm(forms.ModelForm):
    group_type = forms.ChoiceField(
        label="Vous venez seul(e) ou accompagné(e) ?",
        choices=(("alone", "Seul(e)"), ("accompanied", "Accompagné(e)")),
        initial="alone",
    )

    class Meta:
        model = EventRegistration
        fields = (
            "first_name",
            "last_name",
            "age",
            "city",
            "attendance",
            "companions_count",
            "beverages",
            "cardigan_knowledge",
            "intention",
            "desired_size",
            "cardigan_quantity",
            "preferred_color",
            "comment",
        )
        widgets = {
            "first_name": forms.TextInput(attrs={"autocomplete": "given-name"}),
            "last_name": forms.TextInput(attrs={"autocomplete": "family-name"}),
            "age": forms.NumberInput(attrs={"min": 0, "max": 120}),
            "city": forms.TextInput(attrs={"autocomplete": "address-level2"}),
            "companions_count": forms.NumberInput(attrs={"min": 1, "max": 20, "value": 1}),
            "cardigan_quantity": forms.NumberInput(attrs={"min": 1, "max": 20, "value": 1}),
            "desired_size": forms.TextInput(attrs={"placeholder": "Ex. S, M, L, XL"}),
            "comment": forms.Textarea(attrs={"maxlength": 1000, "rows": 4}),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["companions_count"].required = False
        self.fields["cardigan_quantity"].required = False

    def clean(self):
        cleaned_data = super().clean()
        attendance = cleaned_data.get("attendance")
        group_type = cleaned_data.get("group_type")
        intention = cleaned_data.get("intention")
        companions_count = cleaned_data.get("companions_count")

        if attendance == EventRegistration.Attendance.PRESENT and group_type == "accompanied":
            if companions_count is None or not 1 <= companions_count <= 20:
                self.add_error("companions_count", "Indiquez entre 1 et 20 accompagnants.")
        else:
            cleaned_data["companions_count"] = 0

        if intention == EventRegistration.Intention.BUY:
            quantity = cleaned_data.get("cardigan_quantity")
            if quantity is None or not 1 <= quantity <= 20:
                self.add_error("cardigan_quantity", "Indiquez une quantité comprise entre 1 et 20.")
        else:
            cleaned_data["cardigan_quantity"] = 0
        return cleaned_data

    def clean_cardigan_quantity(self):
        return self.cleaned_data.get("cardigan_quantity") or 0

    def clean_companions_count(self):
        return self.cleaned_data.get("companions_count") or 0

    def save(self, commit=True):
        registration = super().save(commit=False)
        if not commit:
            return registration
        registration.save()
        self.save_m2m()
        return registration


class ParticipationForm(forms.Form):
    reference = forms.CharField(label="Référence de l'invitation", max_length=32)
    last_name = forms.CharField(label="Nom de famille", max_length=120)
    attendance = forms.ChoiceField(label="Présence", choices=EventRegistration.Attendance.choices)
    group_type = forms.ChoiceField(
        label="Vous venez seul(e) ou accompagné(e) ?",
        choices=(("alone", "Seul(e)"), ("accompanied", "Accompagné(e)")),
    )
    companions_count = forms.IntegerField(
        label="Nombre d'accompagnants",
        min_value=1,
        max_value=20,
        required=False,
        initial=1,
    )

    def clean(self):
        cleaned_data = super().clean()
        reference = cleaned_data.get("reference", "").strip()
        last_name = cleaned_data.get("last_name", "").strip()
        registration = EventRegistration.objects.filter(reference__iexact=reference).first()
        if registration is None or registration.last_name.casefold() != last_name.casefold():
            raise ValidationError("Référence ou nom invalide. Vérifiez vos informations.")
        cleaned_data["registration"] = registration

        if (
            cleaned_data.get("attendance") == EventRegistration.Attendance.PRESENT
            and cleaned_data.get("group_type") == "accompanied"
        ):
            if cleaned_data.get("companions_count") is None:
                self.add_error("companions_count", "Indiquez le nombre d'accompagnants.")
        else:
            cleaned_data["companions_count"] = 0
        return cleaned_data


class AdminRegistrationForm(forms.ModelForm):
    class Meta:
        model = EventRegistration
        fields = (
            "first_name",
            "last_name",
            "age",
            "city",
            "attendance",
            "companions_count",
            "beverages",
            "cardigan_knowledge",
            "intention",
            "desired_size",
            "cardigan_quantity",
            "preferred_color",
            "comment",
        )
        widgets = {
            "age": forms.NumberInput(attrs={"min": 0, "max": 120}),
            "companions_count": forms.NumberInput(attrs={"min": 0, "max": 20}),
            "cardigan_quantity": forms.NumberInput(attrs={"min": 0, "max": 20}),
            "comment": forms.Textarea(attrs={"maxlength": 1000, "rows": 4}),
        }

    def clean(self):
        cleaned_data = super().clean()
        if cleaned_data.get("attendance") != EventRegistration.Attendance.PRESENT:
            cleaned_data["companions_count"] = 0
        if cleaned_data.get("intention") != EventRegistration.Intention.BUY:
            cleaned_data["cardigan_quantity"] = 0
        return cleaned_data


class OrganizerAuthenticationForm(AuthenticationForm):
    username = forms.CharField(
        label="Adresse e-mail ou identifiant",
        widget=forms.TextInput(attrs={"autocomplete": "username"}),
    )
    password = forms.CharField(
        label="Mot de passe",
        strip=False,
        widget=forms.PasswordInput(attrs={"autocomplete": "current-password"}),
    )
