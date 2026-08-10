import os
import re

def extract_features_from_php(file_path):
    features = []
    with open(file_path, 'r', encoding='utf-8') as file:
        content = file.read()
        matches = re.findall(r"/\*\*(.*?)\*/\s*(public|private|protected)?\s*function\s+(\w+)\s*\((.*?)\)\s*{", content, re.DOTALL)
        for docblock, visibility, function_name, params in matches:
            docblock = re.sub(r"\r\n|\r", "\n", docblock.strip()) 
            lines = [line.strip(" *") for line in docblock.split("\n") if line.strip()]
            description_lines = []
            details = []

            for line in lines:
                if line.startswith("@"):
                    details.append(line + '\n')
                else:
                    description_lines.append(line)

            description = " ".join(description_lines).strip()

            
            function_code_match = re.search(
                rf"(public|private|protected)?\s*function\s+{function_name}\s*\([^)]*\)\s*\{{(.*?)\}}",
                content, re.DOTALL
            )
            function_code = function_code_match.group(0).strip() if function_code_match else ""

            features.append({
                "name": function_name,
                "description": description,
                "details": details,
                "code": function_code
            })
    return features

def generate_features_md(output_path, root_dir):
    features = []
    for root, _, files in os.walk(root_dir):  
        for file in files:
            if file.endswith(".php"):  
                file_path = os.path.join(root, file)
                features.extend(extract_features_from_php(file_path))
    with open(output_path, 'w', encoding='utf-8') as output_file:
        output_file.write("# Features\n\n")
        for feature in features:
            output_file.write(f"## Function Name: {feature['name']}\n\n")
            output_file.write(f"**Description:** {feature['description']}\n\n")
            output_file.write(f"**Function Details:**\n\n")
            for detail in feature['details']:
                output_file.write(f"{detail}\n")
            output_file.write("\n```php\n")
            output_file.write(f"{feature['code']}\n")
            output_file.write("```\n")
            output_file.write("\n<hr>\n\n")

if __name__ == "__main__":
    
    root_dir = os.path.dirname(os.path.abspath(__file__)) 
    generate_features_md(os.path.join(root_dir, "doc/features.md"), root_dir)