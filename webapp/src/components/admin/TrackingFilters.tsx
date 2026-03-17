import React from 'react';
import { Search } from 'lucide-react';

interface TrackingFiltersProps {
    search: string;
    setSearch: (s: string) => void;
    activeTab: string;
    setActiveTab: (t: string) => void;
    tabs: { id: string; label: string; count?: number }[];
}

const TrackingFilters: React.FC<TrackingFiltersProps> = ({ search, setSearch, activeTab, setActiveTab, tabs }) => {
    return (
        <div className="tracking-filters-saas">
            <style>{`
                .tracking-filters-saas {
                    display: flex;
                    flex-direction: column;
                    gap: 24px;
                    margin-bottom: 32px;
                }
                .search-container-saas {
                    position: relative;
                    width: 100%;
                    max-width: 500px;
                }
                .search-container-saas input {
                    width: 100%;
                    padding: 14px 16px 14px 48px;
                    background: rgba(255,255,255,0.03);
                    border: 1px solid var(--border-glass);
                    border-radius: 16px;
                    color: #fff;
                    font-size: 1rem;
                    outline: none;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .search-container-saas input:focus {
                    border-color: var(--accent-primary);
                    background: rgba(255,255,255,0.06);
                    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
                }
                .search-icon-saas {
                    position: absolute;
                    left: 18px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--text-secondary);
                    pointer-events: none;
                    transition: color 0.3s;
                }
                .search-container-saas input:focus + .search-icon-saas {
                    color: var(--accent-primary);
                }

                .tabs-container-saas {
                    display: flex;
                    gap: 8px;
                    background: rgba(255,255,255,0.02);
                    padding: 6px;
                    border-radius: 14px;
                    width: fit-content;
                    border: 1px solid var(--border-glass);
                    position: relative;
                }
                .tab-btn-saas {
                    padding: 10px 20px;
                    border-radius: 10px;
                    border: none;
                    background: transparent;
                    color: var(--text-secondary);
                    font-size: 0.95rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    position: relative;
                }
                .tab-btn-saas:hover {
                    color: #fff;
                }
                .tab-btn-saas.active {
                    color: #fff;
                    background: var(--accent-gradient);
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
                }
                .tab-count-saas {
                    background: rgba(0,0,0,0.3);
                    padding: 2px 8px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 700;
                    transition: background 0.2s;
                }
                .tab-btn-saas.active .tab-count-saas {
                    background: rgba(0,0,0,0.4);
                }
            `}</style>

            <div className="search-container-saas">
                <Search className="search-icon-saas" size={20} />
                <input
                    type="text"
                    placeholder="Pesquisar por código ou cidade..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            <div className="tabs-container-saas">
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        className={`tab-btn-saas ${activeTab === tab.id ? 'active' : ''}`}
                        onClick={() => setActiveTab(tab.id)}
                    >
                        {tab.label} <span className="tab-count-saas">{tab.count || 0}</span>
                    </button>
                ))}
            </div>
        </div>
    );
};

export default TrackingFilters;
