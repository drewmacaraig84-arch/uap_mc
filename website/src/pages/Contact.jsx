import { useState } from 'react';
import { useApi } from '../hooks/useApi';
import { IconMapPin, IconMail, IconPhone, IconMailSent } from '../components/Icons';
import './Contact.css';

export default function Contact() {
  const { data: settings } = useApi('/api/settings.php');
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
  const [sent, setSent] = useState(false);

  const contactEmail = settings?.contact_email || 'uapmindoro@gmail.com';
  const contactAddress = settings?.contact_address || 'Calapan City, Oriental Mindoro, Philippines 5200';
  const contactPhone = settings?.contact_phone || '+63 (0) XXXX XXXX';
  const hoursWeekdays = settings?.office_hours_weekdays || '9:00 AM – 5:00 PM';
  const hoursSaturday = settings?.office_hours_saturday || '9:00 AM – 12:00 PM';
  const hoursSunday = settings?.office_hours_sunday || 'Closed';

  const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const handleSubmit = (e) => {
    e.preventDefault();
    const body = `Name: ${form.name}\nEmail: ${form.email}\n\n${form.message}`;
    window.location.href = `mailto:${contactEmail}?subject=${encodeURIComponent(form.subject || 'Website Inquiry')}&body=${encodeURIComponent(body)}`;
    setSent(true);
  };

  return (
    <main className="page-container">
      {/* Hero */}
      <div className="contact-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Get in Touch</p>
          <h1 className="display-2">Contact <span className="text-gold">Us</span></h1>
          <p className="body-lg muted" style={{ marginTop: 12, maxWidth: 500 }}>
            Reach out to the UAP Mindoro Chapter Secretariat for inquiries, membership, and professional matters.
          </p>
        </div>
      </div>

      <div className="container contact-body">
        <div className="contact-grid">
          {/* Info */}
          <div className="contact-info reveal-left">
            <div className="contact-card card">
              <h2 className="contact-section-title">Chapter Secretariat</h2>

              {[
                {
                  icon: <IconMapPin size={20} />,
                  label: 'Address',
                  value: contactAddress,
                },
                {
                  icon: <IconMail size={20} />,
                  label: 'Email',
                  value: contactEmail,
                  href: `mailto:${contactEmail}`,
                },
                {
                  icon: <IconPhone size={20} />,
                  label: 'Phone',
                  value: contactPhone,
                },
              ].map(({ icon, label, value, href }) => (
                <div key={label} className="contact-info-item">
                  <span className="contact-info-icon">{icon}</span>
                  <div>
                    <p className="contact-info-label">{label}</p>
                    {href ? (
                      <a href={href} className="contact-info-value">{value}</a>
                    ) : (
                      <p className="contact-info-value">{value}</p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            <div className="contact-card card contact-hours">
              <h3 className="contact-section-title" style={{ fontSize: '1rem' }}>Office Hours</h3>
              <div className="hours-grid">
                <span className="muted body-sm">Monday – Friday</span><span className="body-sm">{hoursWeekdays}</span>
                <span className="muted body-sm">Saturday</span><span className="body-sm">{hoursSaturday}</span>
                <span className="muted body-sm">Sunday &amp; Holidays</span><span className="body-sm text-gold">{hoursSunday}</span>
              </div>
            </div>
          </div>

          {/* Form */}
          <div className="contact-form-wrap card reveal-right">
            <h2 className="contact-section-title">Send a Message</h2>
            {sent ? (
              <div className="contact-success">
                <div className="contact-success-icon" style={{ color: 'var(--c-gold)' }}>
                  <IconMailSent size={48} />
                </div>
                <h3>Message Ready!</h3>
                <p className="muted">Your default email client has been opened. Please send the email to complete your inquiry.</p>
                <button className="btn btn-outline" onClick={() => setSent(false)}>Send Another</button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="contact-form">
                <div className="form-row">
                  <div className="form-group">
                    <label htmlFor="name">Full Name</label>
                    <input id="name" name="name" className="input" placeholder="Juan dela Cruz" required value={form.name} onChange={handleChange} />
                  </div>
                  <div className="form-group">
                    <label htmlFor="email">Email Address</label>
                    <input id="email" name="email" type="email" className="input" placeholder="juan@email.com" required value={form.email} onChange={handleChange} />
                  </div>
                </div>
                <div className="form-group">
                  <label htmlFor="subject">Subject</label>
                  <input id="subject" name="subject" className="input" placeholder="Membership Inquiry / Project Consultation / etc." value={form.subject} onChange={handleChange} />
                </div>
                <div className="form-group">
                  <label htmlFor="message">Message</label>
                  <textarea id="message" name="message" className="input" rows="5" placeholder="How can the UAP Mindoro Chapter assist you?" required value={form.message} onChange={handleChange} />
                </div>
                <button type="submit" className="btn btn-gold" style={{ alignSelf: 'flex-start' }}>
                  Send Message
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}
