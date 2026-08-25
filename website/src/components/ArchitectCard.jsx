import { Link } from 'react-router-dom';
import { IconVerified, IconMapPin, IconArrowRight } from './Icons';
import './ArchitectCard.css';

export default function ArchitectCard({ member }) {
  const initials = member.name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <Link to={`/profile/${member.id}`} className="arch-card card">
      <div className="arch-card-photo">
        {member.photo_url ? (
          <img src={member.photo_url} alt={member.name} />
        ) : (
          <div className="arch-card-initials">{initials}</div>
        )}
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
        {member.location && (
          <p className="arch-card-location">
            <IconMapPin size={13} />
            <span>{member.location}</span>
          </p>
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
