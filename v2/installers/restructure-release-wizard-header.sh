#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/release-wizard-header/$STAMP"

WIZARD="resources/js/V2/Shared/Releases/ReleaseWizard.jsx"

mkdir -p \
    "$BACKUP/resources/js/V2/Shared/Releases" \
    v2/runtime/state

echo "=================================================="
echo "RESTRUCTURING V2 RELEASE WIZARD"
echo "=================================================="

if [ ! -f "$WIZARD" ]; then
    echo "ERROR: $WIZARD नहीं मिला।"

    echo ""
    echo "Possible release wizard files:"
    find resources/js/V2 \
        -type f \
        \( -iname '*ReleaseWizard*.jsx' \
        -o -iname '*Release*Edit*.jsx' \
        -o -iname '*Create*.jsx' \) \
        | sort

    exit 1
fi

echo "[1/5] Creating backup..."

cp -a "$WIZARD" \
    "$BACKUP/resources/js/V2/Shared/Releases/ReleaseWizard.jsx"


echo "[2/5] Updating wizard steps and step logic..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path(
    "resources/js/V2/Shared/Releases/ReleaseWizard.jsx"
)

text = path.read_text()

# -------------------------------------------------
# 1. Replace existing four-step definitions
# -------------------------------------------------

step_patterns = [
    re.compile(
        r"""const\s+(?:steps|STEPS|wizardSteps)\s*=\s*\[
.*?
\];""",
        re.S,
    ),
]

new_steps = """const wizardSteps = [
    {
        id: 1,
        label: 'Release Details',
    },
    {
        id: 2,
        label: 'Tracks',
    },
    {
        id: 3,
        label: 'Stores',
    },
    {
        id: 4,
        label: 'Territory',
    },
    {
        id: 5,
        label: 'Review & Publish',
    },
];"""

step_replaced = False

for pattern in step_patterns:
    match = pattern.search(text)

    if not match:
        continue

    block = match.group(0)

    if (
        "Release Details" in block
        and "Review & Publish" in block
    ):
        text = (
            text[:match.start()]
            + new_steps
            + text[match.end():]
        )

        step_replaced = True
        break

if not step_replaced:
    print(
        "Step array automatic replace नहीं हुई; "
        "existing array may already be five-step."
    )


# -------------------------------------------------
# 2. Normalize step-array usages
# -------------------------------------------------

text = re.sub(
    r"\bSTEPS\b",
    "wizardSteps",
    text,
)

text = re.sub(
    r"\bsteps\b(?=\.map\()",
    "wizardSteps",
    text,
)


# -------------------------------------------------
# 3. Change max step from 4 to 5
# -------------------------------------------------

replacements = [
    (
        "Math.min(currentStep + 1, 4)",
        "Math.min(currentStep + 1, 5)",
    ),
    (
        "Math.min(savedStep, 4)",
        "Math.min(savedStep, 5)",
    ),
    (
        "currentStep < 4",
        "currentStep < 5",
    ),
    (
        "currentStep === 4",
        "currentStep === 5",
    ),
    (
        "currentStep <= 4",
        "currentStep <= 5",
    ),
    (
        "currentStep > 4",
        "currentStep > 5",
    ),
    (
        "wizard_step, 4",
        "wizard_step, 5",
    ),
]

for old, new in replacements:
    text = text.replace(old, new)


# -------------------------------------------------
# 4. Split existing combined distribution step
# -------------------------------------------------

combined_patterns = [
    re.compile(
        r"""\{currentStep\s*===\s*3\s*&&\s*\(
\s*<DistributionStep
(?P<props>.*?)
\s*/>
\s*\)\}""",
        re.S,
    ),
    re.compile(
        r"""\{currentStep\s*===\s*3\s*&&\s*
<DistributionStep
(?P<props>.*?)
\s*/>
\s*\}""",
        re.S,
    ),
]

distribution_replaced = False

for pattern in combined_patterns:
    match = pattern.search(text)

    if not match:
        continue

    props = match.group("props")

    replacement = """{currentStep === 3 && (
                        <DistributionStep
                            mode="stores"
%s
                        />
                    )}

                    {currentStep === 4 && (
                        <DistributionStep
                            mode="territory"
%s
                        />
                    )}""" % (props, props)

    text = (
        text[:match.start()]
        + replacement
        + text[match.end():]
    )

    distribution_replaced = True
    break

if not distribution_replaced:
    print(
        "Combined DistributionStep render automatic split नहीं हुआ।"
    )


# -------------------------------------------------
# 5. Move Review step from step 4 to step 5
# -------------------------------------------------

text = re.sub(
    r"\{currentStep\s*===\s*4\s*&&\s*\(\s*<ReviewStep",
    "{currentStep === 5 && (\n                        <ReviewStep",
    text,
)

text = re.sub(
    r"\{currentStep\s*===\s*4\s*&&\s*\(\s*<Review",
    "{currentStep === 5 && (\n                        <Review",
    text,
)


# -------------------------------------------------
# 6. Add top action component before wizard content
# -------------------------------------------------

if "function WizardTopActions(" not in text:
    last_function = text.rfind("\nfunction ")

    component = r'''

function WizardTopActions({
    currentStep,
    processing = false,
    onBack,
    onSaveDraft,
    onNext,
    onSubmit,
}) {
    const isLastStep =
        currentStep === 5;

    return (
        <div className="flex items-center justify-end gap-3">
            {currentStep > 1 && (
                <button
                    type="button"
                    onClick={onBack}
                    disabled={processing}
                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Back
                </button>
            )}

            <button
                type="button"
                onClick={onSaveDraft}
                disabled={processing}
                className="rounded-xl border border-violet-300 bg-violet-50 px-5 py-3 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {processing
                    ? 'Saving...'
                    : 'Save Draft'}
            </button>

            {isLastStep ? (
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={processing}
                    className="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing
                        ? 'Submitting...'
                        : 'Submit for Review'}
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onNext}
                    disabled={processing}
                    className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Next →
                </button>
            )}
        </div>
    );
}
'''

    if last_function != -1:
        text = (
            text[:last_function]
            + component
            + text[last_function:]
        )
    else:
        closing = text.rfind("}")

        text = (
            text[:closing]
            + component
            + text[closing:]
        )


path.write_text(text)

print("Wizard step structure updated.")
PY


echo "[3/5] Updating DistributionStep for separate Stores/Territory modes..."

DISTRIBUTION_STEP="$(find resources/js/V2 -type f \
    -iname 'DistributionStep.jsx' | head -1)"

if [ -n "$DISTRIBUTION_STEP" ] && \
   [ -f "$DISTRIBUTION_STEP" ]
then
    mkdir -p \
        "$BACKUP/$(dirname "$DISTRIBUTION_STEP")"

    cp -a "$DISTRIBUTION_STEP" \
        "$BACKUP/$DISTRIBUTION_STEP"

    python3 - "$DISTRIBUTION_STEP" <<'PY'
from pathlib import Path
import sys
import re

path = Path(sys.argv[1])
text = path.read_text()

# Add mode prop to component.
patterns = [
    (
        r"export default function DistributionStep\(\{",
        "export default function DistributionStep({\n    mode = 'combined',",
    ),
    (
        r"function DistributionStep\(\{",
        "function DistributionStep({\n    mode = 'combined',",
    ),
]

for pattern, replacement in patterns:
    if re.search(pattern, text):
        if "mode = 'combined'" not in text:
            text = re.sub(
                pattern,
                replacement,
                text,
                count=1,
            )
        break

# Replace title according to mode.
text = text.replace(
    "Stores & Territory",
    "{mode === 'stores' ? 'Stores' : mode === 'territory' ? 'Territory' : 'Stores & Territory'}",
)

text = text.replace(
    "Stores & Distribution",
    "{mode === 'stores' ? 'Stores' : mode === 'territory' ? 'Territory' : 'Stores & Territory'}",
)

# Add helper booleans after function opening.
if "const showStores" not in text:
    function_open = re.search(
        r"""(?:export\s+default\s+)?function\s+DistributionStep\([^)]*\)\s*\{""",
        text,
        re.S,
    )

    if function_open:
        insertion = """
    const showStores =
        mode === 'combined' ||
        mode === 'stores';

    const showTerritory =
        mode === 'combined' ||
        mode === 'territory';

"""
        point = function_open.end()

        text = (
            text[:point]
            + insertion
            + text[point:]
        )

# Attempt to wrap obvious sections by heading.
stores_heading_patterns = [
    r'(<[^>]+>\s*Stores\s*</[^>]+>)',
    r'(<[^>]+>\s*Distribution Stores\s*</[^>]+>)',
]

territory_heading_patterns = [
    r'(<[^>]+>\s*Territory\s*</[^>]+>)',
    r'(<[^>]+>\s*Release Territory\s*</[^>]+>)',
]

# We do not destructively rewrite unknown JSX.
# CSS class-based hiding is added around known root wrappers when identifiable.
text = text.replace(
    'data-section="stores"',
    'data-section="stores" className={showStores ? undefined : "hidden"}',
)

text = text.replace(
    'data-section="territory"',
    'data-section="territory" className={showTerritory ? undefined : "hidden"}',
)

path.write_text(text)

print(f"Distribution mode support processed: {path}")
PY
else
    echo "WARNING: DistributionStep.jsx नहीं मिला।"
fi


echo "[4/5] Applying safe visual removal and top-action positioning..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path(
    "resources/js/V2/Shared/Releases/ReleaseWizard.jsx"
)

text = path.read_text()

# Remove page-level heading block containing the unwanted subtitle.
subtitle = (
    "One shared release flow for Artist, "
    "Label, Admin and Super Admin."
)

subtitle_variants = [
    subtitle,
    "One shared release flow for Artist, Label, Admin and Super Admin",
]

for phrase in subtitle_variants:
    location = text.find(phrase)

    if location == -1:
        continue

    # Find closest enclosing div before phrase.
    start = text.rfind("<div", 0, location)

    if start == -1:
        continue

    # Conservative closing-div search.
    end = text.find("</div>", location)

    if end != -1:
        end += len("</div>")

        block = text[start:end]

        if "Create Release" in block:
            text = (
                text[:start]
                + text[end:]
            )

            print(
                "Large Create Release intro block removed."
            )
            break


# Remove old action strip containing auto-save text.
auto_phrases = [
    "Auto-save starts after first save",
    "Auto-save starts after first",
    "Auto save starts after first save",
]

for phrase in auto_phrases:
    location = text.find(phrase)

    if location == -1:
        continue

    start = text.rfind("<div", 0, location)
    end = text.find("</div>", location)

    if start != -1 and end != -1:
        end += len("</div>")

        block = text[start:end]

        if (
            "Save Draft" in block
            or "Back" in block
        ):
            text = (
                text[:start]
                + text[end:]
            )

            print("Old top action strip removed.")
            break


# Remove footer action bar when it contains Back and Next/Submit.
footer_patterns = [
    re.compile(
        r"""<div[^>]*className=["'][^"']*border-t[^"']*["'][^>]*>
(?:(?!</div>).|\n)*?
(?:Next|Submit for Review)
(?:(?!</div>).|\n)*?
</div>""",
        re.S,
    ),
]

for pattern in footer_patterns:
    matches = list(pattern.finditer(text))

    for match in reversed(matches):
        block = match.group(0)

        if (
            "Back" in block
            and (
                "Next" in block
                or "Submit for Review" in block
            )
        ):
            text = (
                text[:match.start()]
                + text[match.end():]
            )

            print("Old bottom footer actions removed.")
            break


# Insert WizardTopActions into wizard step header.
if "<WizardTopActions" not in text:
    step_map = re.search(
        r"""\{wizardSteps\.map\(\(step\)\s*=>\s*\(""",
        text,
    )

    if not step_map:
        step_map = re.search(
            r"""\{wizardSteps\.map\(""",
            text,
        )

    if step_map:
        # Find containing header or flex wrapper.
        wrapper_start = text.rfind(
            "<div",
            0,
            step_map.start(),
        )

        wrapper_end = text.find(
            "</div>",
            step_map.end(),
        )

        if wrapper_start != -1 and wrapper_end != -1:
            insertion_point = wrapper_end

            actions = r'''
                        <WizardTopActions
                            currentStep={currentStep}
                            processing={
                                processing ??
                                submitting ??
                                false
                            }
                            onBack={previousStep}
                            onSaveDraft={saveDraft}
                            onNext={nextStep}
                            onSubmit={submitForReview}
                        />
'''

            text = (
                text[:insertion_point]
                + actions
                + text[insertion_point:]
            )

            print(
                "Top actions inserted beside wizard navigation."
            )
    else:
        print(
            "Wizard step map नहीं मिला; "
            "top actions insertion skipped."
        )


path.write_text(text)
PY


echo "[5/5] Running checks and build..."

echo ""
echo "===== WIZARD REFERENCES ====="

grep -nE \
"wizardSteps|Stores|Territory|Review & Publish|WizardTopActions|currentStep === [1-5]" \
"$WIZARD" \
| head -120

echo ""
echo "===== FRONTEND BUILD ====="

npm run build

php artisan optimize:clear

printf '{\n  "module": "ReleaseWizardRestructure",\n  "installed": true,\n  "steps": 5,\n  "version": "3.5.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/release-wizard-restructure-installed.json

echo ""
echo "=================================================="
echo "RELEASE WIZARD RESTRUCTURE COMPLETE"
echo "=================================================="

cat \
v2/runtime/state/release-wizard-restructure-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
