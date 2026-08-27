import { Link } from 'react-router-dom';
import { IconVerified, IconBriefcase, IconArrowRight, getSocialLinkInfo } from './Icons';
import './ArchitectCard.css';

export default function ArchitectCard({ member }) {
  const initials = member.name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const socialInfo = member.link_url ? getSocialLinkInfo(member.link_url, member.link_type) : null;

  return (
    <Link to={`/profile/${member.id}`} className="arch-card card">
      <div className="arch-card-photo">
        {member.photo_url && (
          <img
            src={member.photo_url}
            alt={member.name}
            onError={(e) => {
              e.currentTarget.style.display = 'none';
              const sib = e.currentTarget.parentElement?.querySelector('.arch-card-initials');
              if (sib) sib.style.display = 'flex';
            }}
          />
        )}
        <div className="arch-card-initials" style={{ display: member.photo_url ? 'none' : 'flex' }}>{initials}</div>
        <div className="arch-card-overlay" />
      </div>

      <div className="arch-card-body">
        <div className="arch-card-badges">
          <span className="badge badge-verified">
            <IconVerified size={13} />
            <span>Good Standing</span>
          </span>
          {member.specialty && (
            <span className="badge badge-gold">{member.specialty.split(',')[0]}</span>
          )}
        </div>
        <h3 className="arch-card-name">{member.name}</h3>
        <p className="arch-card-role">{member.role_title || 'Architect'}</p>

        {member.company_name && (
          <p className="arch-card-company">
            <IconBriefcase size={13} />
            <span>{member.company_name}</span>
          </p>
        )}

        {socialInfo && (
          <div className="arch-card-link-row">
            <a
              href={socialInfo.url}
              target="_blank"
              rel="noopener noreferrer"
              className={`arch-card-social-pill arch-social-${socialInfo.type}`}
              onClick={(e) => e.stopPropagation()}
              title={`Visit ${socialInfo.label}: ${socialInfo.url}`}
            >
              <socialInfo.Icon size={12} />
              <span>{socialInfo.label}</span>
            </a>
          </div>
        )}

        <div className="arch-card-footer">
          <span className="arch-card-prc">PRC: {member.id_number}</span>
          <span className="arch-card-view">
            View Profile <IconArrowRight size={13} style={{ verticalAlign: 'middle', display: 'inline' }} />
          </span>
        </div>
      </div>
    </Link>
  );
}
