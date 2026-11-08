from django.contrib import admin

from .models import EventRegistration


@admin.register(EventRegistration)
class EventRegistrationAdmin(admin.ModelAdmin):
    list_display = (
        "reference",
        "first_name",
        "last_name",
        "city",
        "attendance",
        "companions_count",
        "intention",
        "created_at",
        "checked_in_at",
    )
    list_filter = ("attendance", "beverages", "cardigan_knowledge", "intention")
    search_fields = ("reference", "first_name", "last_name", "city")
    readonly_fields = ("reference", "created_at", "updated_at", "checked_in_at")
