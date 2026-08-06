export default function MascotCharacter({
    activity = 'idle',
}) {
    return (
        <div
            className={`mixx-mascot-character mixx-mascot-${activity}`}
            aria-hidden="true"
        >
            <div className="mixx-mascot-notes">
                <span>♪</span>
                <span>♫</span>
                <span>✦</span>
                <span>♬</span>
            </div>

            <div className="mixx-mascot-particles">
                <span />
                <span />
                <span />
                <span />
                <span />
            </div>

            <svg
                viewBox="0 0 260 300"
                className="h-full w-full"
                role="presentation"
            >
                <defs>
                    <linearGradient
                        id="mixx-jacket"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="#111827"
                        />
                        <stop
                            offset="100%"
                            stopColor="#312e81"
                        />
                    </linearGradient>

                    <linearGradient
                        id="mixx-chair"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="#7c3aed"
                        />
                        <stop
                            offset="100%"
                            stopColor="#4c1d95"
                        />
                    </linearGradient>
                </defs>

                <ellipse
                    cx="132"
                    cy="276"
                    rx="75"
                    ry="13"
                    fill="#cbd5e1"
                    opacity="0.45"
                />

                <g className="mixx-chair">
                    <rect
                        x="72"
                        y="177"
                        width="116"
                        height="85"
                        rx="34"
                        fill="url(#mixx-chair)"
                    />
                    <rect
                        x="83"
                        y="234"
                        width="95"
                        height="35"
                        rx="16"
                        fill="#6d28d9"
                    />
                </g>

                <g className="mixx-body">
                    <path
                        d="M97 128 C94 102, 105 78, 132 75 C160 74, 177 96, 171 127"
                        fill="#1f2937"
                    />

                    <circle
                        cx="134"
                        cy="91"
                        r="36"
                        fill="#f2b58f"
                    />

                    <path
                        d="M100 88 C105 48, 160 42, 173 80 C164 70, 156 68, 148 69 C136 52, 110 63, 100 88Z"
                        fill="#161616"
                    />

                    <path
                        d="M101 88 C92 72, 100 54, 119 48 C107 59, 108 72, 115 80Z"
                        fill="#161616"
                    />

                    <circle
                        cx="122"
                        cy="92"
                        r="3"
                        fill="#111827"
                    />

                    <circle
                        cx="148"
                        cy="92"
                        r="3"
                        fill="#111827"
                    />

                    <path
                        d="M126 107 Q136 116 146 106"
                        fill="none"
                        stroke="#7c2d12"
                        strokeWidth="3"
                        strokeLinecap="round"
                    />

                    <path
                        d="M105 128 Q134 111 164 130 L176 208 Q135 230 92 207Z"
                        fill="url(#mixx-jacket)"
                    />

                    <path
                        d="M128 127 L135 171 L145 127"
                        fill="#ffffff"
                        opacity="0.9"
                    />

                    <path
                        d="M111 149 Q75 162 73 194"
                        fill="none"
                        stroke="#f2b58f"
                        strokeWidth="14"
                        strokeLinecap="round"
                    />

                    <path
                        d="M157 148 Q190 158 194 184"
                        fill="none"
                        stroke="#f2b58f"
                        strokeWidth="14"
                        strokeLinecap="round"
                    />

                    <path
                        d="M111 205 Q104 238 84 259"
                        fill="none"
                        stroke="#111827"
                        strokeWidth="20"
                        strokeLinecap="round"
                    />

                    <path
                        d="M151 207 Q158 239 182 254"
                        fill="none"
                        stroke="#111827"
                        strokeWidth="20"
                        strokeLinecap="round"
                    />
                </g>

                <g className="mixx-guitar">
                    <ellipse
                        cx="103"
                        cy="181"
                        rx="36"
                        ry="29"
                        fill="#d97706"
                        transform="rotate(-14 103 181)"
                    />

                    <ellipse
                        cx="103"
                        cy="181"
                        rx="12"
                        ry="10"
                        fill="#451a03"
                    />

                    <rect
                        x="119"
                        y="151"
                        width="82"
                        height="12"
                        rx="6"
                        fill="#92400e"
                        transform="rotate(-18 119 151)"
                    />

                    <circle
                        cx="193"
                        cy="137"
                        r="10"
                        fill="#78350f"
                    />
                </g>

                <g className="mixx-clipboard">
                    <rect
                        x="83"
                        y="151"
                        width="92"
                        height="74"
                        rx="12"
                        fill="#ffffff"
                        stroke="#cbd5e1"
                        strokeWidth="4"
                    />

                    <rect
                        x="112"
                        y="142"
                        width="35"
                        height="15"
                        rx="6"
                        fill="#7c3aed"
                    />

                    <path
                        d="M101 174 L110 183 L125 166"
                        fill="none"
                        stroke="#10b981"
                        strokeWidth="5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />

                    <path
                        d="M132 176 H158"
                        stroke="#94a3b8"
                        strokeWidth="4"
                        strokeLinecap="round"
                    />

                    <path
                        d="M101 202 L110 211 L125 194"
                        fill="none"
                        stroke="#10b981"
                        strokeWidth="5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />

                    <path
                        d="M132 204 H158"
                        stroke="#94a3b8"
                        strokeWidth="4"
                        strokeLinecap="round"
                    />
                </g>

                <g className="mixx-headphones">
                    <path
                        d="M102 88 C102 47 166 47 167 88"
                        fill="none"
                        stroke="#7c3aed"
                        strokeWidth="9"
                        strokeLinecap="round"
                    />

                    <rect
                        x="94"
                        y="83"
                        width="16"
                        height="35"
                        rx="8"
                        fill="#4c1d95"
                    />

                    <rect
                        x="160"
                        y="83"
                        width="16"
                        height="35"
                        rx="8"
                        fill="#4c1d95"
                    />
                </g>

                <g className="mixx-globe">
                    <circle
                        cx="198"
                        cy="182"
                        r="35"
                        fill="#dbeafe"
                        stroke="#3b82f6"
                        strokeWidth="4"
                    />

                    <path
                        d="M164 182 H232 M198 147 C181 162 181 202 198 217 M198 147 C215 162 215 202 198 217"
                        fill="none"
                        stroke="#3b82f6"
                        strokeWidth="3"
                    />
                </g>

                <g className="mixx-microphone">
                    <rect
                        x="185"
                        y="142"
                        width="8"
                        height="88"
                        rx="4"
                        fill="#334155"
                    />

                    <circle
                        cx="189"
                        cy="135"
                        r="18"
                        fill="#111827"
                    />

                    <path
                        d="M173 232 H205"
                        stroke="#334155"
                        strokeWidth="8"
                        strokeLinecap="round"
                    />
                </g>

                <g className="mixx-coffee">
                    <rect
                        x="174"
                        y="177"
                        width="45"
                        height="34"
                        rx="9"
                        fill="#ffffff"
                        stroke="#cbd5e1"
                        strokeWidth="4"
                    />

                    <path
                        d="M218 184 C236 181 237 204 219 204"
                        fill="none"
                        stroke="#cbd5e1"
                        strokeWidth="5"
                    />

                    <path
                        d="M186 169 C180 160 191 156 185 147"
                        fill="none"
                        stroke="#f59e0b"
                        strokeWidth="3"
                        strokeLinecap="round"
                    />

                    <path
                        d="M201 169 C195 160 206 156 200 147"
                        fill="none"
                        stroke="#f59e0b"
                        strokeWidth="3"
                        strokeLinecap="round"
                    />
                </g>
            </svg>
        </div>
    );
}
