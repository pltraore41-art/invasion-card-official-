from django.contrib.auth import get_user_model
from django.test import TestCase
from django.urls import reverse

from .models import EventRegistration


class RegistrationWorkflowTests(TestCase):
    def registration_data(self, **overrides):
        data = {
            "first_name": "Awa",
            "last_name": "Traore",
            "age": "25",
            "city": "Dakar",
            "attendance": "present",
            "group_type": "alone",
            "beverages": "none",
            "cardigan_knowledge": "new",
            "intention": "discover",
            "desired_size": "M",
        }
        data.update(overrides)
        return data

    def create_registration(self, **overrides):
        values = {
            "first_name": "Awa",
            "last_name": "Traore",
            "age": 25,
            "city": "Dakar",
            "attendance": EventRegistration.Attendance.PRESENT,
            "companions_count": 0,
            "beverages": EventRegistration.Beverage.NONE,
            "cardigan_knowledge": EventRegistration.CardiganKnowledge.NEW,
            "intention": EventRegistration.Intention.DISCOVER,
            "desired_size": "M",
        }
        values.update(overrides)
        return EventRegistration.objects.create(**values)

    def create_admin(self):
        return get_user_model().objects.create_superuser(
            username="organizer",
            email="organizer@example.com",
            password="test-password-that-is-long",
        )

    def test_registration_is_saved_and_receives_a_private_reference(self):
        response = self.client.post(
            reverse("registration_create"),
            self.registration_data(
                group_type="accompanied",
                companions_count="2",
                intention="buy",
                cardigan_quantity="3",
            ),
        )
        registration = EventRegistration.objects.get()
        self.assertRedirects(response, reverse("registration_detail", args=[registration.reference]))
        self.assertRegex(registration.reference, r"^CARD-\d{4}-[A-F0-9]{20}$")
        self.assertEqual(registration.companions_count, 2)
        self.assertEqual(registration.cardigan_quantity, 3)
        self.assertEqual(registration.budget, "45 000 F CFA")

    def test_registration_without_optional_purchase_or_companion_values_succeeds(self):
        response = self.client.post(
            reverse("registration_create"),
            self.registration_data(),
        )
        self.assertEqual(response.status_code, 302)
        registration = EventRegistration.objects.get()
        self.assertEqual(registration.companions_count, 0)
        self.assertEqual(registration.cardigan_quantity, 0)
        self.assertEqual(registration.budget, "")

    def test_registration_rejects_missing_companion_count(self):
        response = self.client.post(
            reverse("registration_create"),
            self.registration_data(group_type="accompanied"),
        )
        self.assertEqual(response.status_code, 200)
        self.assertFalse(EventRegistration.objects.exists())
        self.assertContains(response, "Indiquez entre 1 et 20 accompagnants.")

    def test_participant_can_update_presence_with_reference_and_last_name(self):
        registration = self.create_registration()
        response = self.client.post(
            reverse("participation"),
            {
                "reference": registration.reference.lower(),
                "last_name": "tRAORE",
                "attendance": "present",
                "group_type": "accompanied",
                "companions_count": "3",
            },
        )
        self.assertRedirects(response, reverse("registration_detail", args=[registration.reference]))
        registration.refresh_from_db()
        self.assertEqual(registration.companions_count, 3)

    def test_wrong_last_name_cannot_update_presence(self):
        registration = self.create_registration()
        response = self.client.post(
            reverse("participation"),
            {
                "reference": registration.reference,
                "last_name": "Incorrect",
                "attendance": "absent",
                "group_type": "alone",
                "companions_count": "0",
            },
        )
        self.assertEqual(response.status_code, 200)
        registration.refresh_from_db()
        self.assertEqual(registration.attendance, EventRegistration.Attendance.PRESENT)

    def test_ticket_returns_an_svg_qr_code(self):
        registration = self.create_registration()
        response = self.client.get(reverse("registration_ticket", args=[registration.reference]))
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response["Content-Type"].startswith("image/svg+xml"))
        self.assertIn(b"<svg", response.content)
        self.assertIn(b"Awa Traore", response.content)
        self.assertIn(b"data:image/svg+xml;base64,", response.content)

    def test_participant_list_and_dashboard_require_staff_login(self):
        response = self.client.get(reverse("registration_list"))
        self.assertRedirects(
            response,
            f"{reverse('admin_login')}?next={reverse('registration_list')}",
        )
        response = self.client.get(reverse("admin_dashboard"))
        self.assertEqual(response.status_code, 302)

    def test_staff_can_view_dashboard_and_private_participant_list(self):
        self.create_registration()
        self.client.force_login(self.create_admin())
        response = self.client.get(reverse("admin_dashboard"))
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Acheteurs potentiels")
        response = self.client.get(reverse("registration_list"), {"ville": "daka"})
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Awa Traore")

    def test_staff_can_edit_export_check_in_and_delete(self):
        registration = self.create_registration()
        self.client.force_login(self.create_admin())

        edit_response = self.client.post(
            reverse("admin_registration", args=[registration.reference]),
            {
                "first_name": "Awa",
                "last_name": "Traore",
                "age": "26",
                "city": "Dakar",
                "attendance": "present",
                "companions_count": "1",
                "beverages": "none",
                "cardigan_knowledge": "new",
                "intention": "discover",
                "desired_size": "M",
                "cardigan_quantity": "0",
                "preferred_color": "",
                "comment": "",
            },
        )
        self.assertRedirects(
            edit_response,
            reverse("admin_registration", args=[registration.reference]),
        )
        registration.refresh_from_db()
        self.assertEqual(registration.age, 26)
        self.assertEqual(registration.companions_count, 1)

        export_response = self.client.get(reverse("export_csv"))
        self.assertEqual(export_response.status_code, 200)
        self.assertIn("inscriptions-cardigan.csv", export_response["Content-Disposition"])
        self.assertIn(registration.reference.encode(), export_response.content)

        check_in_response = self.client.post(
            reverse("admin_check_in", args=[registration.reference])
        )
        self.assertRedirects(
            check_in_response,
            reverse("admin_check_in", args=[registration.reference]),
        )
        registration.refresh_from_db()
        self.assertIsNotNone(registration.checked_in_at)

        delete_response = self.client.post(
            reverse("delete_registration", args=[registration.reference])
        )
        self.assertRedirects(delete_response, reverse("admin_dashboard"))
        self.assertFalse(EventRegistration.objects.exists())

    def test_organizer_can_log_in_using_email(self):
        self.create_admin()
        response = self.client.post(
            reverse("admin_login"),
            {"username": "ORGANIZER@example.com", "password": "test-password-that-is-long"},
        )
        self.assertRedirects(response, reverse("admin_dashboard"))
